<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

final class DestroyRoleRequest extends FormRequest
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
                $validator->errors()->add('role', 'Global roles cannot be deleted.');
                return;
            }

            if ((int) $role->team_id !== (int) $org->id) {
                $validator->errors()->add('role', 'You cannot delete roles from another organization.');
                return;
            }

            $assignedCount = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('team_id', $org->id)
                ->count();

            if ($assignedCount > 0) {
                $validator->errors()->add('role', 'This role cannot be deleted because it is assigned to one or more users. Remove the role from all users first.');
            }
        });
    }
}
