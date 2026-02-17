<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

final class DepartmentPolicy
{
    private function scopeTeamId(int $teamId): void
    {
        if ($teamId <= 0) {
            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
    }

    private function currentTeamId(User $user): int
    {
        if (app()->bound('scoped.organization')) {
            $org = app('scoped.organization');

            if ($org instanceof Organization) {
                return (int) $org->id;
            }

            if (is_object($org) && isset($org->id) && is_numeric($org->id)) {
                return (int) $org->id;
            }
        }

        return (int) $user->active_organization_id;
    }

    public function viewAny(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        // Cross-org guard
        if ((int) $user->active_organization_id !== (int) $department->organization_id) {
            return false;
        }

        $this->scopeTeamId((int) $department->organization_id);

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('departments.view');
    }

    public function create(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        $this->scopeTeamId((int) $department->organization_id);

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('departments.update');
    }

    public function delete(User $user, Department $department): bool
    {
        $this->scopeTeamId((int) $department->organization_id);

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('departments.delete');
    }
}
