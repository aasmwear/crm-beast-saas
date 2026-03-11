<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = $this->user();

        return $user->is_super_admin || $user->hasPermissionTo('roles.manage');
    }

    /**
     * Configure the validator.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\Organization $org */
            $org = $this->route('organization');

            /** @var Role $role */
            $role = $this->route('role');

            if ($role->team_id === null) {
                $validator->errors()->add('name', 'Global roles cannot be renamed.');
                return;
            }

            if ((int) $role->team_id !== (int) $org->id) {
                $validator->errors()->add('name', 'You cannot modify roles from another organization.');
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\Organization $org */
        $org = $this->route('organization');

        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->where('team_id', $org->id)
                    ->ignore($role->id),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Role name is required.',
            'name.regex' => 'Role key may only contain lowercase letters, numbers, and hyphens.',
            'name.unique' => 'A role with this key already exists in this organization.',
        ];
    }
}
