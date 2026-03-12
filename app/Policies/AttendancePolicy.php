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

    /**
     * Can view attendance index/history.
     */
    public function viewAny(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('attendance.view') || $user->can('attendance.view-own');
    }

    /**
     * Can view a specific attendance record.
     */
    public function view(User $user, Attendance $attendance): bool
    {
        if ((int) $user->active_organization_id !== (int) $attendance->organization_id) {
            return false;
        }

        $this->scopeTeamId((int) $attendance->organization_id);

        if ($user->is_super_admin) {
            return true;
        }

        if ($user->can('attendance.view')) {
            return true;
        }

        return $user->can('attendance.view-own') && (int) $attendance->user_id === (int) $user->id;
    }

    /**
     * Can clock in (create own attendance record).
     */
    public function clockIn(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('attendance.create') || $user->can('attendance.clock-in');
    }

    /**
     * Can clock out (update own open attendance record).
     */
    public function clockOut(User $user): bool
    {
        $this->scopeTeamId($this->currentTeamId($user));

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('attendance.create') || $user->can('attendance.clock-out');
    }

    /**
     * Can approve attendance (HR/Admin).
     */
    public function approve(User $user, Attendance $attendance): bool
    {
        if ((int) $attendance->organization_id !== (int) $user->active_organization_id) {
            return false;
        }

        $this->scopeTeamId((int) $attendance->organization_id);

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('attendance.manage') || $user->can('attendance.approve');
    }

    /**
     * Can edit/update attendance records (status, notes). HR/Admin can edit others.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        if ((int) $attendance->organization_id !== (int) $user->active_organization_id) {
            return false;
        }

        $this->scopeTeamId((int) $attendance->organization_id);

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('attendance.edit') || $user->can('attendance.manage');
    }

    /**
     * Can delete attendance records.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        if ((int) $attendance->organization_id !== (int) $user->active_organization_id) {
            return false;
        }

        $this->scopeTeamId((int) $attendance->organization_id);

        if ($user->is_super_admin) {
            return true;
        }

        return $user->can('attendance.delete') || $user->can('attendance.manage');
    }
}
