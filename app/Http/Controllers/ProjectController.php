<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectStatusChanged;
use App\Services\ActivityLogger;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController extends Controller
{
    /** @var list<string> Canonical project status values (shared with store/update/pipeline). */
    private const PROJECT_STATUS_VALUES = [
        'Planned', 'Active', 'In Progress', 'Blocked', 'Completed', 'On Hold', 'Cancelled',
    ];

    /**
     * Display a listing of the resource (Grid view).
     *
     * Route: GET /org/{organization:slug}/projects (name: projects.index)
     */
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', Project::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $projectsQuery = Project::with([
            'client:id,company_name',
            'manager:id,name',
            'users:id,name',
        ])
            ->where('organization_id', $organization->id)
            ->visibleTo($user)
            ->latest();

        $projects = $projectsQuery
            ->paginate(25)
            ->withQueryString()
            ->through(function (Project $project): array {
                $progress = (int) ($project->progress_percent ?? 0);

                $teamUsers = $project->users->isNotEmpty()
                    ? $project->users
                    : ($project->manager ? collect([$project->manager]) : collect());
                $teamMembers = $teamUsers->take(3)->map(static function ($u): array {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                    ];
                })->values()->all();
                $teamExtra = max(0, $teamUsers->count() - 3);

                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'status' => $project->status,
                    'client_name' => $project->client?->company_name ?? null,
                    'progress' => $progress,
                    'due_date' => $project->end_date?->format('Y-m-d'),
                    'due_date_formatted' => $project->end_date?->format('M j, Y'),
                    'team' => [
                        'avatars' => $teamMembers,
                        'extra' => $teamExtra,
                    ],
                ];
            });

        $clients = DB::table('clients')
            ->where('organization_id', $organization->id)
            ->orderBy('company_name')
            ->limit(200)
            ->get(['id', 'company_name']);

        $users = DB::table('users')
            ->where('active_organization_id', $organization->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return Inertia::render('Projects/Index', [
            'organization' => $organization->only(['id', 'name', 'slug']),
            'projects' => $projects,
            'clients' => $clients,
            'users' => $users,
            'canCreate' => $user->can('create', Project::class),
        ]);
    }

    /**
     * Display the specified resource (Show view).
     *
     * Route: GET /org/{organization:slug}/projects/{project} (name: projects.show)
     */
    public function show(Request $request, Organization $organization, Project $project): Response
    {
        $this->authorize('view', $project);

        if ((int) $project->organization_id !== (int) $organization->id) {
            abort(404);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Load related data needed for the show page (budget, price, currency from model/accessors)
        // Bounded eager loads to avoid heavy show-page payloads at scale
        $project->load([
            'client:id,company_name',
            'manager:id,name',
            'users:id,name',
            'department:id,name',
            'tasks' => static function ($q) use ($organization, $user): void {
                $q->select(['id', 'project_id', 'title', 'status', 'priority', 'due_date', 'assignees', 'organization_id'])
                    ->where('organization_id', $organization->id)
                    ->visibleTo($user)
                    ->orderByDesc('id')
                    ->limit(200);
            },
            'files' => static fn ($q) => $q
                ->where('organization_id', $organization->id)
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->limit(100),
            'comments' => static function ($q) use ($organization): void {
                $q->where($q->qualifyColumn('organization_id'), $organization->id)
                    ->with('user:id,name')
                    ->orderByDesc('created_at')
                    ->limit(100);
            },
            'activities' => static function ($q) use ($organization): void {
                $q->where($q->qualifyColumn('organization_id'), $organization->id)
                    ->with('user:id,name')
                    ->orderByDesc('created_at')
                    ->limit(100);
            },
        ]);

        // Get all users in this organization for task assignment
        $users = DB::table('users')
            ->where('active_organization_id', $organization->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(200)
            ->get();

        $projectArray = $project->toArray();
        unset($projectArray['files']);
        unset($projectArray['comments']);
        unset($projectArray['activities']);
        if (! $user->can('viewBudget', $project)) {
            foreach (['budget_cents', 'price_cents', 'budget', 'price'] as $key) {
                unset($projectArray[$key]);
            }
        }

        $files = $project->files->map(static fn (\App\Models\ProjectFile $f): array => [
            'id' => $f->id,
            'filename' => $f->filename,
            'path' => $f->path,
            'mime_type' => $f->mime_type,
            'size' => $f->size,
            'is_visible_to_client' => $f->is_visible_to_client,
            'created_at' => $f->created_at?->toIso8601String(),
            'uploader' => $f->user ? ['id' => $f->user->id, 'name' => $f->user->name] : null,
        ])->values()->all();

        $comments = $project->comments->map(static fn (\App\Models\Comment $c): array => [
            'id' => $c->id,
            'body' => $c->body,
            'created_at' => $c->created_at?->toIso8601String(),
            'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name] : null,
        ])->values()->all();

        $activities = $project->activities->map(static fn (\App\Models\Activity $a): array => [
            'id' => $a->id,
            'description' => $a->description,
            'properties' => $a->properties,
            'created_at' => $a->created_at?->toIso8601String(),
            'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
        ])->values()->all();

        return Inertia::render('Projects/Show', [
            'organizationSlug' => $organization->slug,
            'project' => $projectArray,
            'users' => $users,
            'files' => $files,
            'comments' => $comments,
            'activities' => $activities,
            'canEdit' => $user->can('update', $project),
            'canDelete' => $user->can('delete', $project),
            'canManage' => $user->can('manage', $project),
            'canCreateTask' => $user->can('create', \App\Models\Task::class),
        ]);
    }

    /**
     * Show the form for creating a new resource (Create view).
     */
    public function create(Request $request, Organization $organization): Response
    {
        $this->authorize('create', Project::class);

        $clients = DB::table('clients')
            ->where('organization_id', $organization->id)
            ->orderBy('company_name')
            ->limit(200)
            ->get(['id', 'company_name']);

        $cstManagers = DB::table('users')
            ->where('active_organization_id', $organization->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return Inertia::render('Projects/Create', [
            'clients' => $clients,
            'cstManagers' => $cstManagers,
        ]);
    }

    /**
     * Basic project creation (Store logic).
     */
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $request->merge([
            'user_ids' => $request->input('user_ids') ?: [],
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where('organization_id', $organization->id),
            ],
            'status' => ['required', 'string', Rule::in(self::PROJECT_STATUS_VALUES)],
            'due_date' => ['nullable', 'date'],
            'user_ids' => ['array'],
            'user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(static function ($query) use ($organization): void {
                    $query->where('active_organization_id', $organization->id);
                }),
            ],
        ]);

        $project = Project::create([
            'organization_id' => $organization->id,
            'client_id' => $data['client_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'project_manager_id' => ! empty($data['user_ids']) ? (int) $data['user_ids'][0] : null,
            'status' => $data['status'],
            'end_date' => isset($data['due_date']) ? $data['due_date'] : null,
        ]);

        $project->users()->sync($data['user_ids'] ?? []);

        AuditLogger::log(
            $organization,
            $request->user(),
            'created',
            'project',
            (int) $project->id,
            $data,
        );

        return redirect()
            ->route('projects.index', ['organization' => $organization->slug])
            ->with('success', 'Project created successfully.');
    }

    /**
     * Show the form for editing the specified resource (Edit view).
     */
    public function edit(Request $request, Organization $organization, Project $project): Response
    {
        $this->authorize('update', $project);

        $clients = DB::table('clients')
            ->where('organization_id', $organization->id)
            ->orderBy('company_name')
            ->limit(200)
            ->get(['id', 'company_name']);

        $cstManagers = DB::table('users')
            ->where('active_organization_id', $organization->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return Inertia::render('Projects/Edit', [
            'project' => $project,
            'clients' => $clients,
            'cstManagers' => $cstManagers,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where('organization_id', (int) $organization->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'project_code' => ['nullable', 'string', 'max:50'],
            'project_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(static function ($query) use ($organization): void {
                    $query->where('active_organization_id', $organization->id);
                }),
            ],
            'status' => ['required', 'string', Rule::in(self::PROJECT_STATUS_VALUES)],
            'budget' => ['nullable', 'numeric'],
            'price' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'max:3'],
            'billable' => ['boolean'],
        ]);

        $before = $project->getAttributes();

        $project->update($data);

        if (isset($before['status'], $data['status']) && (string) $before['status'] !== (string) $data['status']) {
            $this->notifyProjectStatusChange($project, (string) $before['status'], (string) $data['status'], $request->user()->id);
        }

        AuditLogger::log(
            $organization,
            $request->user(),
            'updated',
            'project',
            (int) $project->id,
            [
                'before' => $before,
                'after' => $project->getAttributes(),
            ],
        );

        return redirect()
            ->route('projects.index', ['organization' => $organization->slug])
            ->with('success', 'Project updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $before = $project->getAttributes();
        $projectId = (int) $project->id;

        $project->delete();

        AuditLogger::log(
            $organization,
            $request->user(),
            'deleted',
            'project',
            $projectId,
            $before,
        );

        return redirect()
            ->route('projects.index', ['organization' => $organization->slug])
            ->with('success', 'Project deleted successfully.');
    }

    /**
     * Handle the project pipeline status update (from a drag-and-drop Board).
     */
    public function updateStatus(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(self::PROJECT_STATUS_VALUES)],
        ]);

        $before = $project->getAttributes();
        $oldStatus = (string) ($before['status'] ?? '');
        $newStatus = (string) $data['status'];

        $project->update(['status' => $newStatus]);

        if ($oldStatus !== $newStatus) {
            ActivityLogger::log(
                $request->user(),
                $project,
                'changed project status',
                ['old_status' => $oldStatus, 'new_status' => $newStatus],
            );
            $this->notifyProjectStatusChange($project, $oldStatus, $newStatus, $request->user()->id);
        }

        AuditLogger::log(
            $organization,
            $request->user(),
            'status_updated',
            'project',
            (int) $project->id,
            [
                'before' => $before,
                'after' => $project->getAttributes(),
            ],
        );

        return back()->with('success', 'Project status updated.');
    }

    /**
     * Notify project manager and team members of a project status change.
     */
    private function notifyProjectStatusChange(Project $project, string $oldStatus, string $newStatus, int $excludeUserId): void
    {
        $project->load(['manager', 'users']);
        $recipients = collect([$project->manager])
            ->merge($project->users)
            ->filter()
            ->unique('id')
            ->where('id', '!=', $excludeUserId);

        $notification = new ProjectStatusChanged($project, $oldStatus, $newStatus);
        $recipients->each(fn (User $u) => $u->notify($notification));
    }
}
