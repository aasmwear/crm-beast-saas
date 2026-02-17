<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * TaskMoved Event.
 *
 * Broadcasts when a task is moved (status or sort order change).
 * Used for real-time Kanban board updates.
 */
final class TaskMoved implements ShouldBroadcast
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
     * @param  string  $newStatus  New task status (todo, in_progress, review, done)
     * @param  string  $newSortOrder  New lexorank sort order
     * @param  int|null  $movedBy  User ID who moved the task
     */
    public function __construct(
        public int $taskId,
        public int $projectId,
        public int $organizationId,
        public string $newStatus,
        public string $newSortOrder,
        public ?int $movedBy = null,
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
        return 'task.moved';
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
            'new_status' => $this->newStatus,
            'new_sort_order' => $this->newSortOrder,
            'moved_by' => $this->movedBy,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
