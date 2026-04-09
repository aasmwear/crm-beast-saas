<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use Illuminate\Validation\ValidationException;

final class CustomFieldValueService
{
    /**
     * Sync custom field values for an entity. Validates against field definitions.
     *
     * @param  array<string, mixed>  $values  Map of field slug => value
     */
    public static function syncForEntity(
        int $organizationId,
        string $entityType,
        int $entityId,
        array $values
    ): void {
        if ($values === []) {
            return;
        }

        $fields = CustomField::query()
            ->forOrg($organizationId)
            ->forEntity($entityType)
            ->whereIn('slug', array_keys($values))
            ->get()
            ->keyBy('slug');

        foreach ($values as $slug => $rawValue) {
            $field = $fields->get($slug);
            if ($field === null) {
                continue; // skip unknown slugs
            }

            $validated = self::validateValue($field, $rawValue);
            if ($validated === null && $field->is_required) {
                throw ValidationException::withMessages([
                    "custom_values.{$slug}" => ["The {$field->label} field is required."],
                ]);
            }

            CustomFieldValue::query()->updateOrCreate(
                [
                    'custom_field_id' => $field->id,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ],
                array_merge(
                    ['organization_id' => $organizationId],
                    self::valueToColumns($field, $validated)
                )
            );
        }
    }

    /**
     * Validate and normalize value for a field type.
     */
    private static function validateValue(CustomField $field, mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($field->type) {
            CustomField::TYPE_NUMBER => is_numeric($raw) ? (float) $raw : null,
            CustomField::TYPE_DATE => is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) ? $raw : null,
            CustomField::TYPE_SELECT => self::validateSelect($field, $raw),
            CustomField::TYPE_MULTISELECT => self::validateMultiselect($field, $raw),
            default => (string) $raw,
        };
    }

    private static function validateSelect(CustomField $field, mixed $raw): ?string
    {
        $options = $field->options ?? [];
        $val = (string) $raw;
        if ($val === '') {
            return null;
        }

        if (! in_array($val, $options, true)) {
            throw ValidationException::withMessages([
                "custom_values.{$field->slug}" => ["The selected value for {$field->label} is invalid."],
            ]);
        }

        return $val;
    }

    private static function validateMultiselect(CustomField $field, mixed $raw): ?array
    {
        $options = $field->options ?? [];
        $arr = is_array($raw) ? $raw : [];
        $invalid = [];
        foreach ($arr as $v) {
            $sv = (string) $v;
            if ($sv !== '' && ! in_array($sv, $options, true)) {
                $invalid[] = $sv;
            }
        }
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                "custom_values.{$field->slug}" => [
                    'One or more selected values for ' . $field->label . ' are invalid.',
                ],
            ]);
        }
        $filtered = array_values(array_filter($arr, fn ($v) => in_array((string) $v, $options, true)));

        return $filtered === [] ? null : $filtered;
    }

    /**
     * @return array{value_text: string|null, value_number: float|null, value_date: string|null, value_json: array|null}
     */
    private static function valueToColumns(CustomField $field, mixed $value): array
    {
        $base = [
            'value_text' => null,
            'value_number' => null,
            'value_date' => null,
            'value_json' => null,
        ];

        if ($value === null) {
            return $base;
        }

        return match ($field->type) {
            CustomField::TYPE_NUMBER => array_merge($base, ['value_number' => (float) $value]),
            CustomField::TYPE_DATE => array_merge($base, ['value_date' => (string) $value]),
            CustomField::TYPE_MULTISELECT => array_merge($base, ['value_json' => $value]),
            default => array_merge($base, ['value_text' => (string) $value]),
        };
    }
}
