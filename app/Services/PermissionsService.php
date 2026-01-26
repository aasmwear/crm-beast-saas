<?php

namespace App\Services;

use App\Models\User;

final class PermissionsService
{
    public function can(User $user, string $entity, string $action): bool
    {
        // Global bypass for platform Super Admins + tenant Owners/Admins
        if ($user->is_super_admin || $user->hasAnyRole(['Owner', 'Admin'])) {
            return true;
        }

        /** @var array<string, mixed> $map */
        $map = $user->permissions_map ?? [];

        return (bool) data_get($map, "{$entity}.actions.{$action}", false);
    }

    public function fieldVisible(User $user, string $entity, string $field): bool
    {
        if ($user->is_super_admin || $user->hasAnyRole(['Owner', 'Admin'])) {
            return true;
        }

        /** @var array<string, mixed> $map */
        $map = $user->permissions_map ?? [];

        return (bool) data_get($map, "{$entity}.fields.{$field}.visible", false);
    }

    public function fieldEditable(User $user, string $entity, string $field): bool
    {
        if ($user->is_super_admin || $user->hasAnyRole(['Owner', 'Admin'])) {
            return true;
        }

        /** @var array<string, mixed> $map */
        $map = $user->permissions_map ?? [];

        return (bool) data_get($map, "{$entity}.fields.{$field}.editable", false);
    }
}
