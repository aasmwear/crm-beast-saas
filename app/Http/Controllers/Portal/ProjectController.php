<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProjectController extends Controller
{
    /**
     * Read-only project view for portal (client) users.
     */
    public function show(Request $request, Project $project): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (empty($user->client_id) || (int) $project->client_id !== (int) $user->client_id) {
            abort(404);
        }

        $project->load([
            'tasks' => fn ($q) => $q->select(['id', 'project_id', 'title', 'status', 'due_date'])->orderBy('id'),
            'files' => fn ($q) => $q->where('is_visible_to_client', true)->orderByDesc('created_at'),
        ]);

        $projectData = $project->only(['id', 'title', 'status', 'description', 'start_date', 'end_date']);
        $projectData['tasks'] = $project->tasks->map(fn ($t) => $t->only(['id', 'title', 'status', 'due_date']));
        $projectData['files'] = $project->files->map(fn ($f) => [
            'id' => $f->id,
            'filename' => $f->filename,
            'path' => $f->path,
            'mime_type' => $f->mime_type,
            'size' => $f->size,
        ])->values()->all();

        return Inertia::render('Portal/ProjectShow', [
            'project' => $projectData,
        ]);
    }

    /**
     * Download a project file (client-visible only).
     */
    public function downloadFile(Request $request, Project $project, ProjectFile $projectFile): StreamedResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (empty($user->client_id) || (int) $project->client_id !== (int) $user->client_id) {
            abort(404);
        }

        if ((int) $projectFile->project_id !== (int) $project->id || ! $projectFile->is_visible_to_client) {
            abort(404);
        }

        $fullPath = Storage::disk('public')->path($projectFile->path);

        if (! file_exists($fullPath)) {
            abort(404, 'File not found.');
        }

        return response()->download(
            $fullPath,
            $projectFile->filename,
            [
                'Content-Type' => $projectFile->mime_type ?? 'application/octet-stream',
            ],
        );
    }
}
