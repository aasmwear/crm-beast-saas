<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $this->sameTenant((int) $project->organization_id);
    }

    public function update(User $user, Project $project): bool
    {
        if (! $this->sameTenant((int) $project->organization_id)) {
            return false;
        }

        return $this->isAdmin($user) || $user->id === (int) $project->project_manager_id;
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
