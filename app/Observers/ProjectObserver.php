<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\Notify;

class ProjectObserver
{
    public function created(Project $project): void
    {
        self::activity($project, 'project_assigned');
    }

    public function updated(Project $project): void
    {
        self::activity($project, 'project_updated');
    }

    protected static function activity(Project $project, string $type): void
    {
        $org = $project->organization;
        if (! $org) {
            return;
        }
        $recipients = [];
        if ($project->project_manager_id) {
            $recipients[] = (int) $project->project_manager_id;
        }

        Notify::push($org, 'new_activity', auth()->id(), array_unique($recipients), 'project', (int) $project->id, [
            'summary' => 'Project updated: '.$project->title,
            'type' => $type,
        ]);
    }
}
