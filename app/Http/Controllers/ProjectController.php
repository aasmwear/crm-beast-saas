<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectController extends Controller
{
    public function board(Request $request): InertiaResponse
    {
        $tenant = App::get('tenant');
        abort_unless($tenant, 404);

        $this->authorize('viewAny', [\App\Models\Project::class, $tenant->id]);

        // Columns for Kanban
        $columns = ['Backlog', 'In Progress', 'Review', 'Done'];

        $projects = DB::table('projects')
            ->where('organization_id', $tenant->id)
            ->select('id', 'title', 'client_id', 'project_manager_id', 'status')
            ->get();

        // tasks grouped by status for the board
        $tasks = DB::table('tasks')
            ->where('organization_id', $tenant->id)
            ->select('id', 'title', 'status', 'project_id', 'priority', 'due_date', 'assignees')
            ->orderBy('priority', 'desc')
            ->orderBy('due_date', 'asc')
            ->get();

        return Inertia::render('Projects/Board', [
            'tenant' => $tenant->only(['id', 'name', 'slug']),
            'columns' => $columns,
            'projects' => $projects,
            'tasks' => $tasks,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = App::get('tenant');
        abort_unless($tenant, 404);

        $this->authorize('create', [\App\Models\Project::class, $tenant->id]);

        // CST-only PM rule: project_manager must belong to CST dept
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'project_manager_id' => [
                'required', 'integer',
                function ($attr, $val, $fail) {
                    $pm = DB::table('users')->where('id', $val)->first();
                    if (! $pm) {
                        return $fail('Invalid PM.');
                    }
                    $dept = DB::table('departments')->where('id', $pm->department_id)->value('code');
                    if ($dept !== 'CST') {
                        $fail('PM must be from CST department.');
                    }
                },
            ],
            'status' => ['required', 'in:Backlog,In Progress,Review,Done'],
        ]);

        $id = DB::table('projects')->insertGetId([
            'organization_id' => $tenant->id,
            'title' => $validated['title'],
            'client_id' => $validated['client_id'],
            'project_manager_id' => $validated['project_manager_id'],
            'status' => $validated['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id], 201);
    }
}
