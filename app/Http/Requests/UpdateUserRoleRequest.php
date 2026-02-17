<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Role;

final class UpdateUserRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $currentUser */
        $currentUser = $this->user();

        /** @var \App\Models\User $targetUser */
        $targetUser = $this->route('user');

        // Use the UserPolicy to check if the current user can assign roles to the target user
        return $currentUser->can('assignRoles', $targetUser);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
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
            'role_ids.required' => 'At least one role must be selected.',
            'role_ids.array' => 'Roles must be provided as an array.',
            'role_ids.*.exists' => 'One or more selected roles are invalid.',
        ];
    }

    /**
     * Perform additional validation after the standard rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\User $currentUser */
            $currentUser = $this->user();

            /** @var \App\Models\User $targetUser */
            $targetUser = $this->route('user');

            /** @var \App\Models\Organization $org */
            $org = $this->route('organization');

            $roleIds = $this->input('role_ids', []);

            if (empty($roleIds)) {
                return;
            }

            // Fetch the roles and verify they belong to this organization
            $roles = Role::query()->whereIn('id', $roleIds)->get();

            foreach ($roles as $role) {
                // Allow global roles (team_id = null) like super-admin only for super-admins
                if ($role->team_id === null) {
                    if ($role->name === 'super-admin' && ! $currentUser->is_super_admin) {
                        $validator->errors()->add('role_ids', "You cannot assign the 'super-admin' role.");

                        return;
                    }
                    // Other global roles are allowed
                    continue;
                }

                // Ensure the role belongs to this organization
                if ((int) $role->team_id !== (int) $org->id) {
                    $validator->errors()->add('role_ids', "You cannot assign roles from another organization.");

                    return;
                }
            }

            // Prevent non-super-admins from assigning super-admin role
            if (! $currentUser->is_super_admin) {
                $hasSuperAdminRole = $roles->contains(function ($role) {
                    return $role->name === 'super-admin';
                });

                if ($hasSuperAdminRole) {
                    $validator->errors()->add('role_ids', 'You cannot assign the super-admin role.');
                }
            }
        });
    }
}
