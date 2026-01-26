<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

final class TaskPolicy
{
    /**
     * Can the user open the Tasks module at all?
     *
     * Row-level visibility is enforced via:
     * - organization scoping in controllers
     * - Task::scopeVisibleTo() on queries
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('tasks.view')) {
            return true;
        }

        // Otherwise allow access if the user has at least one visible task.
        $orgId = (int) $user->active_organization_id;

        return Task::query()
            ->where('organization_id', $orgId)
            ->visibleTo($user)
            ->exists();
    }

    /**
     * View a single task.
     */
    public function view(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('tasks.view')) {
            return true;
        }

        // Enforce row-level visibility (assignees, PMs, client roles) for direct URL access.
        return Task::query()
            ->whereKey($task->id)
            ->where('organization_id', (int) $task->organization_id)
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Create a new task.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('tasks.create');
    }

    /**
     * Update an existing task.
     */
    public function update(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('tasks.update')) {
            return true;
        }

        // Allow assignees (and other row-level visibility cases) to update.
        return Task::query()
            ->whereKey($task->id)
            ->where('organization_id', (int) $task->organization_id)
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Soft-delete a task.
     */
    public function delete(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        return $user->hasAnyRole(['Owner', 'Admin']) || $user->can('tasks.delete');
    }

    /**
     * Submit a task for review.
     */
    public function submit(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('tasks.update')) {
            return true;
        }

        // Submit should be allowed for any user who can update the task (assignees).
        return $this->update($user, $task);
    }

    /**
     * Review a submitted task.
     */
    public function review(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('tasks.review')) {
            return true;
        }

        // Allow the explicitly assigned reviewer (if used) to review.
        return $task->reviewed_by_id !== null && (int) $task->reviewed_by_id === (int) $user->id;
    }
}
