<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CommentController extends Controller
{
    /**
     * Store a new comment on a project.
     */
    public function store(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        if ((int) $project->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:65535'],
        ]);

        Comment::create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'commentable_type' => $project->getMorphClass(),
            'commentable_id' => $project->getKey(),
        ]);

        return back()->with('success', 'Comment added.');
    }

    /**
     * Store a comment on a task.
     */
    public function storeTask(Request $request, Organization $organization, Task $task): RedirectResponse
    {
        $this->authorize('view', $task);

        if ((int) $task->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:65535'],
        ]);

        Comment::create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'commentable_type' => $task->getMorphClass(),
            'commentable_id' => $task->getKey(),
        ]);

        return back()->with('success', 'Comment added.');
    }

    /**
     * Delete a comment (author or admin only).
     */
    public function destroy(Request $request, Organization $organization, Comment $comment): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $comment->load('commentable');
        $commentable = $comment->commentable;

        if ($commentable === null) {
            abort(404);
        }

        $orgId = $commentable instanceof Project
            ? $commentable->organization_id
            : ($commentable instanceof Task ? $commentable->organization_id ?? null : null);

        if ($orgId === null || (int) $orgId !== (int) $organization->id) {
            abort(404);
        }

        $canDelete = $comment->user_id === $user->id || $user->hasAnyRole(['Owner', 'Admin']);

        if (! $canDelete) {
            abort(403, 'Only the author or an admin can delete this comment.');
        }

        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }
}
