<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Task $task,
        private string $assignerName
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
        $this->task->loadMissing(['project', 'organization']);
        $orgId = (int) $this->task->organization_id;
        $projectTitle = $this->task->project?->title ?? 'Project';
        $slug = $this->task->organization?->slug ?? 'acme';

        return [
            'type' => 'task_assigned',
            'message' => "{$this->assignerName} assigned you to task: {$this->task->title}",
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'task_title' => $this->task->title,
            'project_title' => $projectTitle,
            'assigner_name' => $this->assignerName,
            'organization_id' => $orgId,
            'url' => "/org/{$slug}/projects/{$this->task->project_id}?task={$this->task->id}",
        ];
    }
}
