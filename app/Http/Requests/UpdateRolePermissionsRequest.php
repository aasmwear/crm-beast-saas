<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Role;

final class UpdateRolePermissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = $this->user();

        /** @var \App\Models\Organization $org */
        $org = $this->route('organization');

        // Super Admin bypass OR user must have roles.manage permission (team-scoped)
        return $user->is_super_admin || $user->hasPermissionTo('roles.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
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
            'role_id.required' => 'A role must be selected.',
            'role_id.exists' => 'The selected role does not exist.',
            'permission_ids.required' => 'At least one permission must be selected.',
            'permission_ids.*.exists' => 'One or more selected permissions are invalid.',
        ];
    }

    /**
     * Perform additional validation after the standard rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\User $user */
            $user = $this->user();

            /** @var \App\Models\Organization $org */
            $org = $this->route('organization');

            $roleId = $this->input('role_id');
            $role = Role::find($roleId);

            if ($role) {
                // Ensure the role is team-scoped to this organization or is global
                if ($role->team_id !== null && (int) $role->team_id !== (int) $org->id) {
                    $validator->errors()->add('role_id', 'You cannot modify roles from another organization.');
                }

                // Prevent non-super-admins from editing the super-admin role
                if ($role->name === 'super-admin' && ! $user->is_super_admin) {
                    $validator->errors()->add('role_id', 'You cannot modify the super-admin role.');
                }
            }
        });
    }
}
