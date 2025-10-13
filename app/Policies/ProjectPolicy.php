<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Models\Project;

class ProjectPolicy
{
    public function viewAny(User $user, int $orgId): bool
    {
        // allow if Admin or has role in this org or related to any project
        return $user->hasRole(['Admin','ProjectManager','Manager','TeamMember'], $orgId);
    }

    public function view(User $user, Project $project): bool
    {
        $orgId = $project->organization_id;
        if ($user->hasRole(['Admin','ProjectManager','Manager'], $orgId)) return true;

        // Viewer if assigned or PM
        if ((int)$project->project_manager_id === (int)$user->id) return true;

        // assigned via tasks
        return \DB::table('tasks')
            ->where('organization_id', $orgId)
            ->where('project_id', $project->id)
            ->whereJsonContains('assignees', $user->id)
            ->exists();
    }

    public function create(User $user, int $orgId): bool
    {
        return $user->hasRole(['Admin','ProjectManager'], $orgId);
    }

    public function update(User $user, Project $project): bool
    {
        $orgId = $project->organization_id;
        return $user->hasRole(['Admin','ProjectManager'], $orgId)
            || (int)$project->project_manager_id === (int)$user->id;
    }
}
