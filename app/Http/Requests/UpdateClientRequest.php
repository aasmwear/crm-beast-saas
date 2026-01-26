<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Controller/policy handles auth.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:160'],

            'industry' => ['nullable', 'string', 'max:160'],
            'niche' => ['nullable', 'string', 'max:160'],

            'primary_contact_name' => ['nullable', 'string', 'max:255'],
            'primary_contact_email' => ['nullable', 'email', 'max:255'],
            'primary_contact_phone' => ['nullable', 'string', 'max:50'],

            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],

            'fronter' => ['nullable', 'array'],
            'fronter.*' => ['integer'],

            'closer' => ['nullable', 'array'],
            'closer.*' => ['integer'],

            'assigned_account_manager_id' => ['nullable', 'integer', 'exists:users,id'],

            'google_business_profile_status' => ['nullable', 'string', 'max:100'],
            'google_business_profile_access_status' => ['nullable', 'string', 'max:100'],

            'client_activation_status' => ['nullable', 'string', 'max:50'],

            'notes_by_cst' => ['nullable', 'string'],
            'notes_by_sales' => ['nullable', 'string'],
            'notes_by_tech' => ['nullable', 'string'],

            'status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
