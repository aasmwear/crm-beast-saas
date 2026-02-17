<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class ProjectStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Project $project,
        private string $oldStatus,
        private string $newStatus
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->project->loadMissing(['organization']);
        $orgId = (int) $this->project->organization_id;
        $slug = $this->project->organization?->slug ?? 'acme';

        return [
            'type' => 'project_status_changed',
            'message' => "Project \"{$this->project->title}\" status changed from {$this->oldStatus} to {$this->newStatus}.",
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'organization_id' => $orgId,
            'url' => "/org/{$slug}/projects/{$this->project->id}",
        ];
    }
}
