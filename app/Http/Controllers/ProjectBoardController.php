<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectBoardController extends Controller
{
    /**
     * Display the Kanban board view for projects.
     *
     * Route: GET /org/{organization:slug}/projects/board (name: projects.board)
     */
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', Project::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $projects = Project::with(['manager:id,name'])
            ->where('organization_id', $organization->id)
            ->visibleTo($user)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Projects/Board', [
            'organization' => $organization->only(['id', 'name', 'slug']),
            'projects' => $projects->map(static function (Project $project): array {
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'status' => $project->status,
                    'project_manager_id' => $project->project_manager_id,
                    'project_manager_name' => optional($project->manager)->name,
                ];
            })->values(),
        ]);
    }
}
