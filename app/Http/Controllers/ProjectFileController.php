<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Services\ActivityLogger;
use App\Services\Billing\StorageUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ProjectFileController extends Controller
{
    /**
     * Store a new file.
     */
    public function store(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        if ((int) $project->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB
        ]);

        /** @var \Illuminate\Http\UploadedFile $uploaded */
        $uploaded = $request->file('file');

        if (app(StorageUsageService::class)->wouldExceedLimit($organization, (int) $uploaded->getSize())) {
            throw ValidationException::withMessages([
                'file' => ['Storage limit reached for your plan.'],
            ]);
        }
        $filename = $uploaded->getClientOriginalName();
        $path = $uploaded->store(
            "project_files/{$project->id}",
            'public',
        );

        if ($path === false) {
            return back()->with('error', 'Failed to store file.');
        }

        $projectFile = ProjectFile::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'filename' => $filename,
            'path' => $path,
            'mime_type' => $uploaded->getMimeType(),
            'size' => $uploaded->getSize(),
            'is_visible_to_client' => false,
        ]);

        ActivityLogger::log(
            $request->user(),
            $project,
            'uploaded a file',
            ['filename' => $filename],
        );

        return back()->with('success', 'File uploaded successfully.');
    }

    /**
     * Delete a file from DB and storage.
     */
    public function destroy(Request $request, Organization $organization, Project $project, ProjectFile $projectFile): RedirectResponse
    {
        $this->authorize('update', $project);

        if ((int) $project->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $projectFile = $this->resolveScopedProjectFile($organization, $project, $projectFile);

        Storage::disk('public')->delete($projectFile->path);
        $projectFile->delete();

        return back()->with('success', 'File deleted.');
    }

    /**
     * Toggle visibility to client portal.
     */
    public function toggleVisibility(Request $request, Organization $organization, Project $project, ProjectFile $projectFile): RedirectResponse
    {
        $this->authorize('update', $project);

        if ((int) $project->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $projectFile = $this->resolveScopedProjectFile($organization, $project, $projectFile);

        $projectFile->update([
            'is_visible_to_client' => ! $projectFile->is_visible_to_client,
        ]);

        return back()->with('success', 'Visibility updated.');
    }

    /**
     * Force download of a file.
     */
    public function download(Request $request, Organization $organization, Project $project, ProjectFile $projectFile): BinaryFileResponse|Response
    {
        $this->authorize('view', $project);

        if ((int) $project->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $projectFile = $this->resolveScopedProjectFile($organization, $project, $projectFile);

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

    private function resolveScopedProjectFile(Organization $organization, Project $project, ProjectFile $projectFile): ProjectFile
    {
        return ProjectFile::query()
            ->whereKey($projectFile->id)
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->firstOrFail();
    }
}
