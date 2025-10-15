<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function update(User $user, Task $task): bool
    {
        if (! $this->sameTenant((int) $task->organization_id)) {
            return false;
        }

        // Normalize assignees (casted to array in model) to int[]
        $assignees = array_map('intval', (array) $task->assignees);

        return in_array((int) $user->id, $assignees, true) || $this->isAdmin($user);
    }

    private function sameTenant(int $orgId): bool
    {
        /** @var \App\Models\Organization|null $tenant */
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        return $tenant !== null && (int) $tenant->id === $orgId;
    }

    private function isAdmin(User $user): bool
    {
        return (bool) $user->hasRole('admin');
    }
}
