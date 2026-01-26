<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TaskBoardController extends Controller
{
    /**
     * Display the Kanban board view for tasks.
     *
     * Route: GET /org/{organization:slug}/tasks/board (name: tasks.board)
     */
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', Task::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $tasks = Task::query()
            ->with(['project:id,organization_id,title'])
            ->where('organization_id', $organization->id)
            ->visibleTo($user)
            ->orderByRaw("CASE
                WHEN status IS NULL OR status = '' THEN 0
                WHEN lower(status) IN ('todo', 'open', 'new', 'backlog') THEN 1
                WHEN lower(status) IN ('in progress', 'doing', 'active') THEN 2
                WHEN lower(status) IN ('submitted', 'pending review') THEN 3
                WHEN lower(status) IN ('approved') THEN 4
                WHEN lower(status) IN ('changes requested') THEN 5
                WHEN lower(status) IN ('rejected') THEN 6
                WHEN lower(status) IN ('done', 'completed') THEN 7
                ELSE 8
            END")
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Tasks/Board', [
            'organization' => $organization->only(['id', 'name', 'slug']),
            'tasks' => $tasks->map(function (Task $task) use ($user): array {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date?->toDateString(),
                    'project' => [
                        'id' => $task->project->id,
                        'title' => $task->project->title,
                    ],
                    'can_update' => $user->can('update', $task),
                ];
            })->values(),
        ]);
    }
}
