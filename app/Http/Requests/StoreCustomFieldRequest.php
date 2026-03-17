<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('custom-fields.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\Organization $org */
        $org = $this->route('organization');

        return [
            'label' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('custom_fields', 'slug')
                    ->where('organization_id', $org->id)
                    ->where('entity', \App\Models\CustomField::ENTITY_CLIENT),
            ],
            'type' => ['required', 'string', Rule::in(CustomField::TYPES)],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
