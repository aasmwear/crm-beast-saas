<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

final class AttendancePolicy
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

        // Users can view their own attendance or those with permission can view all
        return $user->can('attendance.view') || $user->can('attendance.view-own');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        // Cross-org guard
        if ((int) $user->active_organization_id !== (int) $attendance->organization_id) {
            return false;
        }

        $this->scopeTeamId((int) $attendance->organization_id);

        if ($user->is_super_admin) {
            return true;
        }

        // Can view if they have the general permission or if it's their own
        if ($user->can('attendance.view')) {
            return true;
        }

        return $user->can('attendance.view-own') && (int) $attendance->user_id === (int) $user->id;
    }

    public function clockIn(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('attendance.clock-in');
    }

    public function clockOut(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('attendance.clock-out');
    }

    public function approve(User $user, Attendance $attendance): bool
    {
        $this->scopeTeamId((int) $attendance->organization_id);

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('attendance.approve');
    }

    public function update(User $user, Attendance $attendance): bool
    {
        $this->scopeTeamId((int) $attendance->organization_id);

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('attendance.manage');
    }
}
