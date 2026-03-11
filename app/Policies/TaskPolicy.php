<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

final class TaskPolicy
{
    /**
     * Can the user open the Tasks module at all?
     *
     * Row-level visibility is enforced via Task::scopeVisibleTo() on queries.
     */
    public function viewAny(User $user): bool
    {
        if ($user->is_super_admin ?? false) {
            return true;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('tasks.view')) {
            return true;
        }

        return false;
    }

    /**
     * View a single task.
     */
    public function view(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('tasks.view')) {
            return true;
        }

        // Row-level visibility: assignees/PMs without tasks.view can view tasks they belong to.
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
        if ($user->is_super_admin ?? false) {
            return true;
        }

        return $user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('tasks.create');
    }

    /**
     * Update an existing task (including move/status).
     */
    public function update(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM'])) {
            return true;
        }

        return $user->can('tasks.edit') || $user->can('tasks.update');
    }

    /**
     * Soft-delete a task.
     */
    public function delete(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        return $user->hasAnyRole(['Owner', 'Admin']) || $user->can('tasks.delete');
    }

    /**
     * Submit a task for review (edit-level action).
     */
    public function submit(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM'])) {
            return true;
        }

        return $user->can('tasks.edit') || $user->can('tasks.update');
    }

    /**
     * Review a submitted task (special permission or edit-level).
     */
    public function review(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM'])) {
            return true;
        }

        if ($user->can('tasks.review') || $user->can('tasks.edit') || $user->can('tasks.update')) {
            return true;
        }

        // Allow the explicitly assigned reviewer (if used) to review.
        return $task->reviewed_by_id !== null && (int) $task->reviewed_by_id === (int) $user->id;
    }

    /**
     * Bulk or special management actions.
     */
    public function manage(User $user, Task $task): bool
    {
        if ((int) $user->active_organization_id !== (int) $task->organization_id) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        return $user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM'])
            || $user->can('tasks.manage')
            || $user->can('tasks.edit')
            || $user->can('tasks.update');
    }
}
