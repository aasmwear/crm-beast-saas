<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

final class UserPolicy
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

    /**
     * Determine if the user can view the user management list.
     */
    public function viewAny(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('users.view') || $user->can('users.manage')
            || $user->can('hrm.view') || $user->can('hrm.manage');
    }

    /**
     * Determine if the user can view a specific user's details.
     */
    public function view(User $user, User $targetUser): bool
    {
        // Cross-org guard
        if ((int) $user->active_organization_id !== (int) $targetUser->active_organization_id) {
            return false;
        }

        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('users.view') || $user->can('users.manage')
            || $user->can('hrm.view') || $user->can('hrm.manage');
    }

    /**
     * Determine if the user can create new users.
     */
    public function create(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('users.create') || $user->can('hrm.create') || $user->can('hrm.manage');
    }

    /**
     * Determine if the user can update another user's details.
     */
    public function update(User $user, User $targetUser): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        // Prevent non-super-admins from editing super-admin users
        if ($targetUser->is_super_admin && ! $user->is_super_admin) {
            return false;
        }

        return $user->can('users.update') || $user->can('users.manage')
            || $user->can('hrm.edit') || $user->can('hrm.manage');
    }

    /**
     * Determine if the user can delete another user.
     */
    public function delete(User $user, User $targetUser): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        // Prevent self-deletion
        if ((int) $user->id === (int) $targetUser->id) {
            return false;
        }

        // Prevent non-super-admins from deleting super-admin users
        if ($targetUser->is_super_admin && ! $user->is_super_admin) {
            return false;
        }

        return $user->can('users.delete') || $user->can('hrm.delete') || $user->can('hrm.manage');
    }

    /**
     * Determine if the user can assign roles to another user.
     */
    public function assignRoles(User $user, User $targetUser): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        // Prevent self role changes (unless super admin)
        if ((int) $user->id === (int) $targetUser->id) {
            return false;
        }

        // Prevent non-super-admins from modifying super-admin users
        if ($targetUser->is_super_admin && ! $user->is_super_admin) {
            return false;
        }

        return $user->can('users.assign-roles') || $user->can('users.manage')
            || $user->can('hrm.edit') || $user->can('hrm.manage');
    }
}
