<?php

namespace App\Http\Controllers;

use App\Events\TaskMoved;
use App\Events\TaskUpdated;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\ActivityLogger;
use App\Services\AuditLogger;
use App\Services\ProjectTaskProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class TaskController extends Controller
{
    /**
     * Tasks index with filters + pagination.
     *
     * Route: GET /org/{organization:slug}/tasks
     *
     * Query filters:
     * - status: string|null
     * - mine: bool (1/0)
     * - project_id: int|null
     * - due_from: Y-m-d|null
     * - due_to: Y-m-d|null
     * - search: string|null
     */
    public function index(Request $request): Response
    {
        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        $this->authorize('viewAny', Task::class);

        /** @var User $user */
        $user = $request->user();

        $filters = [
            'status' => $request->query('status'),
            'mine' => $request->boolean('mine'),
            'project_id' => $request->query('project_id'),
            'due_from' => $request->query('due_from'),
            'due_to' => $request->query('due_to'),
            'search' => $request->query('search'),
        ];

        $query = Task::query()
            ->with(['project:id,organization_id,title'])
            ->where('organization_id', (int) $org->id)
            ->visibleTo($user);

        if (! empty($filters['status'])) {
            $status = strtolower((string) $filters['status']);
            $query->whereRaw('lower(status) = ?', [$status]);
        }

        if ($filters['mine']) {
            $query->whereJsonContains('assignees', (int) $user->id);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }

        if (! empty($filters['due_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_from']);
        }

        if (! empty($filters['due_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_to']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function ($sub) use ($search): void {
                // Postgres-friendly case-insensitive search
                $sub->where('title', 'ilike', '%'.$search.'%')
                    ->orWhere('description', 'ilike', '%'.$search.'%');
            });
        }

        $tasks = $query
            ->orderByDesc('due_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $projects = Project::query()
            ->where('organization_id', (int) $org->id)
            ->visibleTo($user)
            ->orderBy('title')
            ->limit(200)
            ->get(['id', 'title']);

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'projects' => $projects,
            'canCreate' => $user->can('create', Task::class),
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        $this->authorize('create', Task::class);

        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('organization_id', $org->id)],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assignees' => 'array',
            'assignees.*' => 'integer',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:50',
            'estimated_hours' => 'nullable|numeric',
        ]);

        $data['organization_id'] = (int) $org->id;

        return DB::transaction(function () use ($data, $org, $request): RedirectResponse {
            $task = Task::create($data);
            $currentUserId = $request->user()->id;
            $assignerName = $request->user()->name;

            AuditLogger::log(
                $org,
                $request->user(),
                'created',
                'task',
                (int) $task->id,
                $data,
            );

            $assigneeIds = array_filter(array_unique($data['assignees'] ?? []));
            if ($assigneeIds !== []) {
                User::query()
                    ->whereIn('id', $assigneeIds)
                    ->where('id', '!=', $currentUserId)
                    ->get()
                    ->each(fn (User $u) => $u->notify(new TaskAssigned($task, $assignerName)));
            }

            return back()->with('success', 'Task created');
        });
    }

    /**
     * Show a single task as JSON (for async detail panels, etc.).
     * Organization is route-model bound for scopeBindings; task is scoped to it.
     */
    public function show(Request $request, Organization $organization, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        if ((int) $task->organization_id !== (int) $organization->id) {
            abort(404);
        }

        /** @var \App\Models\Organization $org */
        $org = $organization;

        /** @var User $user */
        $user = $request->user();

        $task->load([
            'project:id,organization_id,title',
            'reviewer:id,name',
        ]);

        /**
         * project_id is required in our schema, so project should be present.
         * Still, keep it defensive at runtime; but type it for PHPStan.
         *
         * @var \App\Models\Project $project
         */
        $project = $task->project;

        /** @var User|null $reviewer */
        $reviewer = $task->reviewer;

        return response()->json([
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->toDateString(),
            'estimated_hours' => $task->estimated_hours,
            'logged_hours' => $task->logged_hours,

            'project' => [
                'id' => $project->id,
                'title' => $project->title,
            ],

            'submission' => $task->submission,
            'submission_note' => $task->submission_note,
            'submission_files' => $task->submission_files,
            'review_status' => $task->review_status,
            'comments' => $task->comments,
            'reviewed_by_id' => $task->reviewed_by_id,

            'reviewer' => $reviewer
                ? [
                    'id' => $reviewer->id,
                    'name' => $reviewer->name,
                ]
                : null,

            // auth middleware guarantees user, so no ternary
            'can_update' => $user->can('update', $task),
            'can_submit' => $user->can('submit', $task),
            'can_review' => $user->can('review', $task),
            'can_delete' => $user->can('delete', $task),
        ]);
    }

    /**
     * Update a task's core fields, submission or review metadata.
     */
    public function update(Request $request, Organization $organization, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        if ((int) $task->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'assignees' => 'array',
            'assignees.*' => 'integer',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:50',
            'estimated_hours' => 'nullable|numeric',
            'logged_hours' => 'nullable|numeric',
            'submission' => 'array',
            'submission_note' => 'nullable|string',
            'submission_files' => 'nullable|array',
            'review_status' => 'nullable|string|max:50',
            'comments' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($task, $data, $organization, $request): RedirectResponse {
            $before = $task->getAttributes();

            $update = [];

            $fields = [
                'title',
                'description',
                'assignees',
                'due_date',
                'priority',
                'status',
                'estimated_hours',
                'logged_hours',
                'submission',
                'submission_note',
                'submission_files',
                'review_status',
                'comments',
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $update[$field] = $data[$field];
                }
            }

            if ($update === []) {
                return back()->with('success', 'Task updated');
            }

            $task->update($update);

            $after = $task->getAttributes();

            if (array_key_exists('assignees', $update)) {
                $currentUserId = $request->user()->id;
                $assignerName = $request->user()->name;
                $assigneeIds = array_filter(array_unique((array) ($task->assignees ?? [])));
                if ($assigneeIds !== []) {
                    User::query()
                        ->whereIn('id', $assigneeIds)
                        ->where('id', '!=', $currentUserId)
                        ->get()
                        ->each(fn (User $u) => $u->notify(new TaskAssigned($task, $assignerName)));
                }
            }

            AuditLogger::log(
                $organization,
                $request->user(),
                'updated',
                'task',
                (int) $task->id,
                [
                    'before' => $before,
                    'after' => $after,
                ],
            );

            // ========================================
            // REALTIME EVENT DISPATCHING
            // ========================================

            // Detect if status changed (task was moved)
            $statusChanged = array_key_exists('status', $update) && ($before['status'] ?? null) !== ($after['status'] ?? null);

            if ($statusChanged) {
                $newStatus = (string) ($after['status'] ?? '');
                $oldStatus = (string) ($before['status'] ?? '');
                if (ProjectTaskProgressService::isTaskStatusCompleted($newStatus)) {
                    $task->load('project');
                    ActivityLogger::log(
                        $request->user(),
                        $task,
                        'completed the task',
                        ['old_status' => $oldStatus, 'new_status' => $newStatus, 'task_title' => $task->title],
                    );
                    // Also log on project for project activity feed
                    if ($task->project) {
                        ActivityLogger::log(
                            $request->user(),
                            $task->project,
                            'task completed: ' . $task->title,
                            ['task_id' => $task->id, 'old_status' => $oldStatus, 'new_status' => $newStatus],
                        );
                    }
                }

                // Dispatch TaskMoved event for Kanban board updates
                TaskMoved::dispatch(
                    (int) $task->id,
                    (int) $task->project_id,
                    (int) $task->organization_id,
                    (string) $after['status'],
                    (string) ($after['sort_order'] ?? ''),
                    (int) $request->user()->id,
                );

                // Audit log for status change
                AuditLogger::log(
                    $organization,
                    $request->user(),
                    'status_changed',
                    'task',
                    (int) $task->id,
                    [
                        'before' => $before['status'] ?? null,
                        'after' => $after['status'] ?? null,
                    ],
                );
            }

            // Detect if other fields changed (not just status)
            $detailChanges = [];
            foreach (['title', 'description', 'priority', 'due_date', 'assignees', 'estimated_hours'] as $field) {
                if (array_key_exists($field, $update) && ($before[$field] ?? null) !== ($after[$field] ?? null)) {
                    $detailChanges[$field] = $after[$field];
                }
            }

            if (! empty($detailChanges)) {
                // Dispatch TaskUpdated event for detail changes
                TaskUpdated::dispatch(
                    (int) $task->id,
                    (int) $task->project_id,
                    (int) $task->organization_id,
                    $detailChanges,
                    (int) $request->user()->id,
                );
            }

            // ========================================
            // END REALTIME EVENT DISPATCHING
            // ========================================

            if (array_key_exists('assignees', $update)) {
                $beforeAssignees = $before['assignees'] ?? null;
                $afterAssignees = $after['assignees'] ?? null;

                if ($beforeAssignees !== $afterAssignees) {
                    AuditLogger::log(
                        $organization,
                        $request->user(),
                        'assignees_changed',
                        'task',
                        (int) $task->id,
                        [
                            'before' => $beforeAssignees,
                            'after' => $afterAssignees,
                        ],
                    );
                }
            }

            return back()->with('success', 'Task updated');
        });
    }

    /**
     * Soft-delete a task.
     */
    public function destroy(Request $request, Organization $organization, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        if ((int) $task->organization_id !== (int) $organization->id) {
            abort(404);
        }

        return DB::transaction(function () use ($task, $organization, $request): RedirectResponse {
            $before = $task->getAttributes();
            $taskId = (int) $task->id;

            $task->delete();

            AuditLogger::log(
                $organization,
                $request->user(),
                'deleted',
                'task',
                $taskId,
                $before,
            );

            return back()->with('success', 'Task deleted');
        });
    }

    /**
     * Submit a task for review.
     */
    public function submit(Request $request, Organization $organization, Task $task): RedirectResponse
    {
        $this->authorize('submit', $task);

        if ((int) $task->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $data = $request->validate([
            'submission' => 'required|array',
            'submission_note' => 'nullable|string',
            'submission_files' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($task, $data, $organization, $request): RedirectResponse {
            $before = $task->getAttributes();

            $update = [
                'submission' => $data['submission'],
                'review_status' => 'Pending',
            ];

            if (array_key_exists('submission_note', $data)) {
                $update['submission_note'] = $data['submission_note'];
            }

            if (array_key_exists('submission_files', $data)) {
                $update['submission_files'] = $data['submission_files'];
            }

            $task->update($update);

            $after = $task->getAttributes();

            AuditLogger::log(
                $organization,
                $request->user(),
                'submitted',
                'task',
                (int) $task->id,
                [
                    'before' => $before,
                    'after' => $after,
                ],
            );

            return back()->with('success', 'Submitted for review');
        });
    }

    /**
     * Review a submitted task (approve, request changes, reject).
     */
    public function review(Request $request, Organization $organization, Task $task): RedirectResponse
    {
        $this->authorize('review', $task);

        if ((int) $task->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $data = $request->validate([
            'review_status' => 'required|string|in:Approved,Changes Requested,Rejected',
            'comments' => 'array',
        ]);

        /** @var User $user */
        $user = $request->user();

        return DB::transaction(function () use ($task, $data, $organization, $user): RedirectResponse {
            $before = $task->getAttributes();

            $update = [
                'review_status' => $data['review_status'],
                'comments' => $data['comments'] ?? $task->comments,
                'reviewed_by_id' => $user->id,
            ];

            $task->update($update);

            $after = $task->getAttributes();

            if (($data['review_status'] ?? '') === 'Approved') {
                $task->load('project');
                ActivityLogger::log(
                    $user,
                    $task,
                    'approved the task',
                    ['review_status' => 'Approved'],
                );
                if ($task->project) {
                    ActivityLogger::log(
                        $user,
                        $task->project,
                        'task approved: ' . $task->title,
                        ['task_id' => $task->id],
                    );
                }
            }

            AuditLogger::log(
                $organization,
                $user,
                'reviewed',
                'task',
                (int) $task->id,
                [
                    'before' => $before,
                    'after' => $after,
                ],
            );

            return back()->with('success', 'Review saved');
        });
    }
}
