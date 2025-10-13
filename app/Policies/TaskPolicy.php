<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function update(User $user, Task $task): bool
    {
        $orgId = $task->organization_id;

        if ($user->hasRole(['Admin','ProjectManager','Manager'], $orgId)) return true;

        return \in_array($user->id, $task->assignees ?? []);
    }

    public function create(User $user, int $orgId): bool
    {
        return $user->hasRole(['Admin','ProjectManager','Manager'], $orgId);
    }
}
