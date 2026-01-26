<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TaskSubmissionController extends Controller
{
    public function submit(Request $request, string $organization, Task $task): RedirectResponse
    {
        $org = Organization::query()->where('slug', $organization)->firstOrFail();
        abort_unless((int) $task->organization_id === (int) $org->id, 404);

        $data = $request->validate([
            'submission_note' => ['nullable', 'string'],
            'files.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $files = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('submissions', ['disk' => 'public']);
                $files[] = ['path' => $path, 'name' => $file->getClientOriginalName()];
            }
        }

        $task->update([
            'submission_note' => $data['submission_note'] ?? null,
            'submission_files' => $files ?: null,
            'review_status' => 'pending',
        ]);

        return back();
    }

    public function review(Request $request, string $organization, Task $task): RedirectResponse
    {
        $org = Organization::query()->where('slug', $organization)->firstOrFail();
        abort_unless((int) $task->organization_id === (int) $org->id, 404);

        $data = $request->validate([
            'review_status' => ['required', 'in:approved,changes_requested'],
        ]);

        $task->update([
            'review_status' => $data['review_status'],
            'reviewed_by_id' => $request->user()->id,
        ]);

        return back();
    }
}
