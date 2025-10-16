<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\MoveTaskRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    /**
     * Create a new task in the given organization & project.
     */
    public function store(Request $request, Organization $organization): RedirectResponse|JsonResponse
    {
        $this->authorize('create', [Task::class, $organization]);

        /**
         * @phpstan-var array{
         *   project_id:int,
         *   title:string,
         *   status?:string|null,
         *   assignees?:array<array-key, mixed>|null,
         *   due_date?:string|\DateTimeInterface|null
         * } $data
         */
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'assignees' => ['nullable', 'array'],
            'due_date' => ['nullable', 'date'],
        ]);

        /** @var Project $project */
        $project = Project::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($data['project_id']);

        /** @var int|null $maxIndex */
        $maxIndex = DB::table('tasks')
            ->where('project_id', $project->id)
            ->max('order_index');

        $nextIndex = is_null($maxIndex) ? 0 : $maxIndex + 1;

        $task = new Task;
        $task->organization_id = $organization->id;
        $task->project_id = $project->id;
        $task->title = $data['title'];
        $task->status = $data['status'] ?? null;
        $task->assignees = $data['assignees'] ?? null;
        // Cast string date to Carbon to satisfy Larastan's typed property expectations
        $task->due_date = isset($data['due_date']) ? Carbon::parse((string) $data['due_date']) : null;
        $task->order_index = $nextIndex;
        $task->save();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'task' => $task->only(['id', 'project_id', 'title', 'status', 'order_index']),
            ]);
        }

        return redirect()
            ->route('projects.board', ['organization' => $organization->slug])
            ->with('success', 'Task created.');
    }

    /**
     * Move (and optionally re-order) a task within/between projects.
     */
    public function move(MoveTaskRequest $request, Organization $organization, Task $task): JsonResponse
    {
        $this->authorize('update', [$task, $organization]);
        abort_unless($task->organization_id === $organization->id, 404);

        /**
         * @phpstan-var array{
         *   to_project_id:int,
         *   before_id?:int|null,
         *   status?:string|null
         * } $data
         */
        $data = $request->validated();

        /** @var Project $targetProject */
        $targetProject = Project::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($data['to_project_id']);

        /** @var int|null $oldIndex */
        $oldIndex = $task->order_index ?? null;

        /** @var int|null $targetMax */
        $targetMax = DB::table('tasks')
            ->where('project_id', $targetProject->id)
            ->max('order_index');

        $targetIndex = is_null($targetMax) ? 0 : $targetMax + 1;

        if (! empty($data['before_id'])) {
            /** @var Task $beforeTask */
            $beforeTask = Task::query()
                ->where('organization_id', $organization->id)
                ->where('project_id', $targetProject->id)
                ->findOrFail((int) $data['before_id']);

            /** @var int|null $beforeIdx */
            $beforeIdx = $beforeTask->order_index ?? 0;
            $targetIndex = $beforeIdx;
        }

        DB::transaction(function () use ($task, $organization, $targetProject, $oldIndex, $targetIndex, $data): void {
            $sameProject = $task->project_id === $targetProject->id;

            if ($sameProject) {
                $currentIndex = $oldIndex ?? 0;

                if ($targetIndex > $currentIndex) {
                    DB::table('tasks')
                        ->where('project_id', $task->project_id)
                        ->where('organization_id', $organization->id)
                        ->whereBetween('order_index', [$currentIndex + 1, $targetIndex])
                        ->decrement('order_index');
                } elseif ($targetIndex < $currentIndex) {
                    DB::table('tasks')
                        ->where('project_id', $task->project_id)
                        ->where('organization_id', $organization->id)
                        ->whereBetween('order_index', [$targetIndex, $currentIndex - 1])
                        ->increment('order_index');
                }

                $task->order_index = $targetIndex;
                if (array_key_exists('status', $data)) {
                    $task->status = $data['status'];
                }
                $task->save();
            } else {
                if (! is_null($oldIndex)) {
                    DB::table('tasks')
                        ->where('project_id', $task->project_id)
                        ->where('organization_id', $organization->id)
                        ->where('order_index', '>', $oldIndex)
                        ->decrement('order_index');
                }

                DB::table('tasks')
                    ->where('project_id', $targetProject->id)
                    ->where('organization_id', $organization->id)
                    ->where('order_index', '>=', $targetIndex)
                    ->increment('order_index');

                $task->project_id = $targetProject->id;
                $task->order_index = $targetIndex;
                if (array_key_exists('status', $data)) {
                    $task->status = $data['status'];
                }
                $task->save();
            }
        });

        return response()->json([
            'ok' => true,
            'task' => $task->only(['id', 'project_id', 'order_index', 'status']),
        ]);
    }
}
