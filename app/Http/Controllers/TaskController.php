<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $tenant = App::get('tenant');
        abort_unless($tenant, 404);
        $this->authorize('create', [Task::class, $tenant->id]);

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:200'],
            'status' => ['required', 'in:Backlog,In Progress,Review,Done'],
            'priority' => ['nullable', 'integer', 'between:0,5'],
            'due_date' => ['nullable', 'date'],
            'assignees' => ['array'],
            'assignees.*' => ['integer', 'exists:users,id'],
        ]);

        $id = DB::table('tasks')->insertGetId([
            'organization_id' => $tenant->id,
            'project_id' => $data['project_id'],
            'title' => $data['title'],
            'status' => $data['status'],
            'priority' => $data['priority'] ?? 0,
            'due_date' => $data['due_date'] ?? null,
            'assignees' => json_encode($data['assignees'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id], 201);
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        $tenant = App::get('tenant');
        abort_unless($tenant, 404);
        abort_if($task->organization_id !== $tenant->id, 403);

        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => ['required', 'in:Backlog,In Progress,Review,Done'],
        ]);

        $task->update([
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);

        return response()->json(null, 204);
    }
}
