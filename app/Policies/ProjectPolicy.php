<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

final class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->is_super_admin ?? false) {
            return true;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('projects.view')) {
            return true;
        }

        return false;
    }

    public function view(User $user, Project $project): bool
    {
        if ((int) $user->active_organization_id !== (int) $project->organization_id) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('projects.view')) {
            return true;
        }

        // Row-level visibility: task assignees/PMs without projects.view can view projects they belong to.
        return Project::query()
            ->whereKey($project->id)
            ->where('organization_id', (int) $project->organization_id)
            ->visibleTo($user)
            ->exists();
    }

    public function create(User $user): bool
    {
        if ($user->is_super_admin ?? false) {
            return true;
        }

        return $user->hasAnyRole(['Owner', 'Admin', 'PM']) || $user->can('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        if ((int) $user->active_organization_id !== (int) $project->organization_id) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        return $user->hasAnyRole(['Owner', 'Admin', 'PM'])
            || $user->can('projects.edit')
            || $user->can('projects.update');
    }

    public function delete(User $user, Project $project): bool
    {
        if ((int) $user->active_organization_id !== (int) $project->organization_id) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        return $user->hasAnyRole(['Owner', 'Admin']) || $user->can('projects.delete');
    }

    /**
     * Whether the user may perform bulk or special management actions (e.g. pipeline bulk update).
     */
    public function manage(User $user, Project $project): bool
    {
        if ((int) $user->active_organization_id !== (int) $project->organization_id) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        return $user->hasAnyRole(['Owner', 'Admin', 'PM'])
            || $user->can('projects.manage')
            || $user->can('projects.edit')
            || $user->can('projects.update');
    }

    /**
     * Whether the user may see budget/price (financial) data for the project.
     * Used before sending budget_cents, price_cents, budget, price to the frontend.
     */
    public function viewBudget(User $user, Project $project): bool
    {
        if ((int) $user->active_organization_id !== (int) $project->organization_id) {
            return false;
        }

        return $user->can('financials.view');
    }
}
