<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AnnouncementsController extends Controller
{
    public function index(Request $request, string $organization): Response
    {
        $org = Organization::query()->where('slug', $organization)->firstOrFail();
        $rows = Announcement::query()->where('organization_id', $org->id)->orderByDesc('published_at')->limit(100)->get();

        return Inertia::render('Announcements/Index', ['items' => $rows]);
    }

    public function store(Request $request, string $organization): RedirectResponse
    {
        $org = Organization::query()->where('slug', $organization)->firstOrFail();
        $user = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'pinned' => ['nullable', 'boolean'],
        ]);

        $data['organization_id'] = $org->id;
        $data['user_id'] = $user->id;
        $data['published_at'] = now();

        Announcement::query()->create($data);

        return back();
    }
}
