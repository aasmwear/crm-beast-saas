<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

final class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        // Owners/Admins/PM/AM can always access Projects.
        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('projects.view')) {
            return true;
        }

        // Otherwise allow access only if the user has at least one visible project.
        // This prevents the Projects module from hard-403 for team members who only
        // participate via tasks (e.g. Tech) and don't have a privileged role.
        $orgId = (int) $user->active_organization_id;

        return Project::query()
            ->where('organization_id', $orgId)
            ->visibleTo($user)
            ->exists();
    }

    public function view(User $user, Project $project): bool
    {
        // Cross-org guard
        if ((int) $user->active_organization_id !== (int) $project->organization_id) {
            return false;
        }

        if ($user->hasAnyRole(['Owner', 'Admin', 'PM', 'AM']) || $user->can('projects.view')) {
            return true;
        }

        // Enforce row-level visibility for direct URL access.
        return Project::query()
            ->whereKey($project->id)
            ->where('organization_id', (int) $project->organization_id)
            ->visibleTo($user)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Admin', 'PM']) || $user->can('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        if ((int) $user->active_organization_id !== (int) $project->organization_id) {
            return false;
        }

        return $user->hasAnyRole(['Owner', 'Admin', 'PM']) || $user->can('projects.update');
    }

    public function delete(User $user, Project $project): bool
    {
        if ((int) $user->active_organization_id !== (int) $project->organization_id) {
            return false;
        }

        return $user->hasAnyRole(['Owner', 'Admin']) || $user->can('projects.delete');
    }
}
