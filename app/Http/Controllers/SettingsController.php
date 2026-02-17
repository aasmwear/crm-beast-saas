<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    /**
     * Display the organization settings page.
     */
    public function index(Request $request): Response
    {
        /** @var Organization $organization */
        $organization = $request->route('organization');

        return Inertia::render('Settings/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'logo_path' => $organization->logo_path,
                'timezone' => $organization->timezone ?? 'UTC',
                'week_start' => $organization->week_start ?? 'Monday',
            ],
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::ALL),
        ]);
    }

    /**
     * Update organization settings.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var Organization $organization */
        $organization = $request->route('organization');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone'],
            'week_start' => ['required', 'string', 'in:Sunday,Monday'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ]);

        $organization->name = $validated['name'];
        $organization->timezone = $validated['timezone'];
        $organization->week_start = $validated['week_start'];

        if ($request->hasFile('logo')) {
            $dir = 'logos';
            if (! Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }

            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }

            $path = $request->file('logo')->store($dir, 'public');
            $organization->logo_path = $path;
        }

        $organization->save();

        return redirect()
            ->route('settings.index', ['organization' => $organization->slug])
            ->with('success', 'Settings updated');
    }
}
