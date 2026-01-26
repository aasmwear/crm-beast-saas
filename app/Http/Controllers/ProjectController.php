<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController extends Controller
{
    /**
     * Display a listing of the resource (Index view).
     *
     * Route: GET /org/{organization:slug}/projects (name: projects.index)
     */
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', Project::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Eager load client & manager; scope by tenant + visibility
        $projects = Project::with(['client:id,company_name', 'manager:id,name'])
            ->where('organization_id', $organization->id)
            ->visibleTo($user)
            ->latest()
            ->simplePaginate(10)
            ->withQueryString();

        // Any user in this org can be selected as PM
        $cstManagers = DB::table('users')
            ->where('active_organization_id', $organization->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Projects/Index', [
            'organization' => $organization->only(['id', 'name', 'slug']),
            'projects' => $projects,
            'cstManagers' => $cstManagers,
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

        // Load related data needed for the show page
        $project->load([
            'client:id,company_name',
            'manager:id,name',
            'department:id,name',
            // Keep tasks lean for the summary and enforce row-level visibility.
            'tasks' => static function ($q) use ($organization, $user): void {
                $q->select(['id', 'project_id', 'title', 'status', 'priority', 'due_date', 'organization_id'])
                    ->where('organization_id', $organization->id)
                    ->visibleTo($user)
                    ->orderByDesc('id');
            },
        ]);

        return Inertia::render('Projects/Show', [
            'organizationSlug' => $organization->slug,
            'project' => $project,
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
            ->get(['id', 'company_name']);

        $cstManagers = DB::table('users')
            ->where('active_organization_id', $organization->id)
            ->select('id', 'name')
            ->orderBy('name')
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

        $data = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'project_code' => ['nullable', 'string', 'max:255'],
            'project_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(static function ($query) use ($organization): void {
                    $query->where('active_organization_id', $organization->id);
                }),
            ],
            'price' => ['nullable', 'numeric'],
            'billable' => ['boolean'],
        ]);

        $project = Project::create([
            'organization_id' => $organization->id,
            'client_id' => $data['client_id'] ?? null,
            'title' => $data['title'],
            'project_code' => $data['project_code'] ?? null,
            'project_manager_id' => $data['project_manager_id'] ?? null,
            'price' => $data['price'] ?? 0,
            'billable' => $data['billable'] ?? false,
            'status' => 'Active',
        ]);

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
            ->get(['id', 'company_name']);

        $cstManagers = DB::table('users')
            ->where('active_organization_id', $organization->id)
            ->select('id', 'name')
            ->orderBy('name')
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
            'client_id' => ['nullable', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'project_code' => ['nullable', 'string', 'max:50'],
            'project_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(static function ($query) use ($organization): void {
                    $query->where('active_organization_id', $organization->id);
                }),
            ],
            'status' => ['required', 'string', 'max:50'],
            // Other fields (description, budget, etc.) can be added later
        ]);

        $before = $project->getAttributes();

        $project->update($data);

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
            'status' => ['required', 'string', 'max:50'],
        ]);

        $before = $project->getAttributes();

        $project->update(['status' => $data['status']]);

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
}
