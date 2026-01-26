<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProjectMessagesController extends Controller
{
    /**
     * Show all messages for a project.
     *
     * Route (inside /org/{organization:slug} group):
     * GET /projects/{project}/messages  → projects.messages.index
     */
    public function index(Request $request, Organization $organization, Project $project): Response
    {
        $this->authorize('view', $project);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $rootMessages = ProjectMessage::with([
            'user:id,name',
            'replies.user:id,name',
        ])
            ->where('project_id', $project->id)
            ->whereNull('parent_id')
            ->orderBy('created_at')
            ->get();

        // Build a plain array of threads suitable for Inertia
        $threads = $rootMessages
            ->map(static function (ProjectMessage $message): array {
                $replies = $message->replies
                    ->sortBy('created_at')
                    ->map(static function (ProjectMessage $reply): array {
                        return [
                            'id' => $reply->id,
                            'body' => $reply->body ?? null,
                            'created_at' => $reply->created_at?->toIso8601String(),
                            'user' => [
                                'id' => $reply->user?->id,
                                'name' => $reply->user?->name,
                            ],
                            'attachments' => $reply->attachments ?? [],
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $message->id,
                    'body' => $message->body ?? null,
                    'created_at' => $message->created_at?->toIso8601String(),
                    'user' => [
                        'id' => $message->user?->id,
                        'name' => $message->user?->name,
                    ],
                    'attachments' => $message->attachments ?? [],
                    'replies' => $replies,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Projects/Messages', [
            'organization' => $organization->only(['id', 'name', 'slug']),
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
            ],
            'threads' => $threads,
            'canPost' => $user->can('update', $project),
        ]);
    }

    /**
     * Store a new message (or reply) for a project.
     *
     * POST /projects/{project}/messages  → projects.messages.store
     */
    public function store(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validate([
            'body' => ['nullable', 'string'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('project_messages', 'id')->where(static function ($query) use ($project): void {
                    $query->where('project_id', $project->id);
                }),
            ],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'], // 10MB per file
        ]);

        $body = $data['body'] ?? '';
        $hasAttachments = $request->hasFile('attachments');

        // Require at least a message or an attachment
        if ($body === '' && ! $hasAttachments) {
            return back()->with('error', 'Message cannot be empty.');
        }

        $storedAttachments = [];

        if ($hasAttachments) {
            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('project-messages/'.$project->id, 'public');

                $storedAttachments[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                ];
            }
        }

        ProjectMessage::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'author_id' => $user->id, // legacy column, required in your DB
            'user_id' => $user->id,   // new column used by the current model/spec
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $body !== '' ? $body : null,
            'attachments' => $storedAttachments ?: null,
        ]);

        return redirect()
            ->route('projects.messages.index', [
                'organization' => $organization->slug,
                'project' => $project->id,
            ])
            ->with('success', 'Message posted.');
    }

    /**
     * Download a single attachment from a message.
     *
     * GET /projects/{project}/messages/{message}/download/{index}
     *   → projects.messages.download
     */
    public function download(
        Request $request,
        Organization $organization,
        Project $project,
        string $message,
        int $index
    ): StreamedResponse {
        $this->authorize('view', $project);

        /** @var ProjectMessage|null $projectMessage */
        $projectMessage = ProjectMessage::query()
            ->where('project_id', $project->id)
            ->whereKey($message)
            ->first();

        if ($projectMessage === null) {
            abort(404);
        }

        $attachments = $projectMessage->attachments ?? [];

        if (! array_key_exists($index, $attachments)) {
            abort(404);
        }

        $raw = $attachments[$index];

        if (! is_array($raw) || ! isset($raw['path'])) {
            abort(404);
        }

        $path = (string) $raw['path'];
        $downloadName = isset($raw['name']) && is_string($raw['name'])
            ? $raw['name']
            : basename($path);

        return Storage::disk('public')->download($path, $downloadName);
    }
}
