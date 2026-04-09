<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Custom field value for an entity instance.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $custom_field_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string|null $value_text
 * @property float|null $value_number
 * @property string|null $value_date
 * @property array|null $value_json
 */
final class CustomFieldValue extends Model
{
    protected $fillable = [
        'organization_id',
        'custom_field_id',
        'entity_type',
        'entity_id',
        'value_text',
        'value_number',
        'value_date',
        'value_json',
    ];

    protected $casts = [
        'value_number' => 'float',
        'value_json' => 'array',
    ];

    /**
     * @return BelongsTo<CustomField, CustomFieldValue>
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    /**
     * @return BelongsTo<Organization, CustomFieldValue>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getValue(): mixed
    {
        return match ($this->customField->type ?? 'text') {
            CustomField::TYPE_NUMBER => $this->value_number,
            CustomField::TYPE_DATE => $this->value_date,
            CustomField::TYPE_SELECT => $this->value_text,
            CustomField::TYPE_MULTISELECT => $this->value_json ?? [],
            default => $this->value_text,
        };
    }

    public function setValue(mixed $value): void
    {
        $type = $this->customField->type ?? CustomField::TYPE_TEXT;
        $this->value_text = null;
        $this->value_number = null;
        $this->value_date = null;
        $this->value_json = null;

        match ($type) {
            CustomField::TYPE_NUMBER => $this->value_number = $value !== null && $value !== '' ? (float) $value : null,
            CustomField::TYPE_DATE => $this->value_date = $value !== null && $value !== '' ? (string) $value : null,
            CustomField::TYPE_MULTISELECT => $this->value_json = is_array($value) ? $value : [],
            default => $this->value_text = $value !== null ? (string) $value : null,
        };
    }
}
