<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateRolePermissionsMatrixRequest extends FormRequest
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
            'matrix' => ['required', 'array'],
            'matrix.*' => ['array'],
            'matrix.*.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * Configure the validator.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\User $user */
            $user = $this->user();

            /** @var \App\Models\Organization $org */
            $org = $this->route('organization');

            $matrix = $this->input('matrix', []);
            foreach (array_keys($matrix) as $roleIdKey) {
                $roleId = is_numeric($roleIdKey) ? (int) $roleIdKey : null;
                if ($roleId === null) {
                    $validator->errors()->add('matrix', "Invalid role id key: {$roleIdKey}.");
                    continue;
                }

                $role = Role::find($roleId);
                if (! $role) {
                    $validator->errors()->add('matrix', "Role id {$roleId} does not exist.");
                    continue;
                }

                if ($role->team_id !== null && (int) $role->team_id !== (int) $org->id) {
                    $validator->errors()->add('matrix', "You cannot modify role id {$roleId} (another organization).");
                }

                if (in_array($role->name, ['super-admin', 'Super Admin'], true) && ! $user->is_super_admin) {
                    $validator->errors()->add('matrix', 'You cannot modify the super-admin role.');
                }
            }
        });
    }
}
