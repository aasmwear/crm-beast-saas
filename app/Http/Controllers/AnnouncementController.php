<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    /**
     * GET /org/{organization:slug}/announcements
     */
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', Announcement::class);

        $announcements = Announcement::query()
            ->forOrganization($organization->id)
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->through(static function (Announcement $announcement): array {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'pinned' => (bool) $announcement->pinned,
                    'created_at' => optional($announcement->created_at)->toIso8601String(),
                ];
            });

        return Inertia::render('Announcements/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'announcements' => $announcements,
        ]);
    }

    /**
     * POST /org/{organization:slug}/announcements
     */
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'pinned' => ['sometimes', 'boolean'],
        ]);

        $data['organization_id'] = $organization->id;
        $data['author_id'] = $request->user()->id;
        $data['pinned'] = $request->boolean('pinned');

        Announcement::create($data);

        return redirect()
            ->route('announcements.index', ['organization' => $organization->slug])
            ->with('success', 'Announcement posted.');
    }

    /**
     * PUT /org/{organization:slug}/announcements/{announcement}
     */
    public function update(
        Request $request,
        Organization $organization,
        Announcement $announcement
    ): RedirectResponse {
        if ((int) $announcement->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $this->authorize('update', $announcement);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'pinned' => ['sometimes', 'boolean'],
        ]);

        $data['pinned'] = $request->boolean('pinned');

        $announcement->update($data);

        return redirect()
            ->route('announcements.index', ['organization' => $organization->slug])
            ->with('success', 'Announcement updated.');
    }

    /**
     * DELETE /org/{organization:slug}/announcements/{announcement}
     */
    public function destroy(
        Request $request,
        Organization $organization,
        Announcement $announcement
    ): RedirectResponse {
        if ((int) $announcement->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $this->authorize('delete', $announcement);

        $announcement->delete();

        return redirect()
            ->route('announcements.index', ['organization' => $organization->slug])
            ->with('success', 'Announcement deleted.');
    }

    /**
     * GET /org/{organization:slug}/announcements/{announcement}
     *
     * For now we don't have a dedicated "show" page, so we just bounce
     * back to the index. This keeps existing routes happy.
     */
    public function show(
        Request $request,
        Organization $organization,
        Announcement $announcement
    ): RedirectResponse {
        return redirect()
            ->route('announcements.index', ['organization' => $organization->slug]);
    }
}
