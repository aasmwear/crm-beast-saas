<?php

namespace App\Observers;

use App\Models\Task;

final class TaskObserver
{
    /**
     * Handle the Task "creating" event.
     */
    public function creating(Task $task): void
    {
        if ($task->status === null || $task->status === '') {
            $task->status = 'todo';
        }

        if ($task->priority === null || $task->priority === '') {
            $task->priority = 'medium';
        }
    }

    /**
     * Handle the Task "updating" event.
     */
    public function updating(Task $task): void
    {
        // Reserved for future logic (e.g., audit, notifications).
    }
}
