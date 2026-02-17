<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * TaskUpdated Event.
 *
 * Broadcasts when a task's details are updated (title, description, assignees, etc.).
 * Used for real-time task detail updates.
 */
final class TaskUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  int  $taskId  Task ID
     * @param  int  $projectId  Project ID (for channel routing)
     * @param  int  $organizationId  Organization ID (for tenant isolation)
     * @param  array<string, mixed>  $changes  Changed fields (e.g., ['title' => 'New Title', 'priority' => 'high'])
     * @param  int|null  $updatedBy  User ID who updated the task
     */
    public function __construct(
        public int $taskId,
        public int $projectId,
        public int $organizationId,
        public array $changes,
        public ?int $updatedBy = null,
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("org.{$this->organizationId}.projects.{$this->projectId}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'task.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->taskId,
            'project_id' => $this->projectId,
            'changes' => $this->changes,
            'updated_by' => $this->updatedBy,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
