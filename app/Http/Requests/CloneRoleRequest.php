<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

final class CloneRoleRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
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
                $validator->errors()->add('role', 'Global roles cannot be cloned.');
                return;
            }

            if ((int) $role->team_id !== (int) $org->id) {
                $validator->errors()->add('role', 'You cannot clone roles from another organization.');
            }
        });
    }
}
