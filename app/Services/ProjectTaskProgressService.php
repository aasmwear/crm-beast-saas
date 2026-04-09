<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Task;

/**
 * Denormalized task counts / progress on projects (v1). Recalculate from live tasks after writes.
 *
 * "Completed" matches project list UX: done, completed, closed, finished (case-insensitive).
 */
final class ProjectTaskProgressService
{
    /** @var list<string> */
    private const COMPLETED_STATUSES = ['done', 'completed', 'closed', 'finished'];

    public static function isTaskStatusCompleted(?string $status): bool
    {
        if ($status === null || $status === '') {
            return false;
        }

        $s = strtolower(trim($status));

        return in_array($s, self::COMPLETED_STATUSES, true);
    }

    /**
     * Recompute denormalized columns from non-trashed tasks for this project.
     */
    public static function recalculateForProjectId(int $projectId): void
    {
        if ($projectId <= 0) {
            return;
        }

        if (! Project::query()->whereKey($projectId)->exists()) {
            return;
        }

        $base = Task::query()->where('project_id', $projectId);
        $tasksCount = (int) (clone $base)->count();

        $parts = [];
        $bindings = [];
        foreach (self::COMPLETED_STATUSES as $st) {
            $parts[] = 'lower(trim(coalesce(status, ?))) = ?';
            $bindings[] = '';
            $bindings[] = $st;
        }

        $completedCount = (int) (clone $base)
            ->whereRaw('(' . implode(' OR ', $parts) . ')', $bindings)
            ->count();

        $openCount = max(0, $tasksCount - $completedCount);
        $progressPercent = $tasksCount > 0
            ? (int) round(($completedCount / $tasksCount) * 100)
            : 0;

        Project::query()->whereKey($projectId)->update([
            'tasks_count' => $tasksCount,
            'open_tasks_count' => $openCount,
            'completed_tasks_count' => $completedCount,
            'progress_percent' => $progressPercent,
        ]);
    }
}
