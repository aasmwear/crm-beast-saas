<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OrganizationSettingsController extends Controller
{
    public function show(Organization $organization): InertiaResponse
    {
        $this->authorize('update', [Organization::class, $organization]);

        return Inertia::render('Organizations/Settings', [
            'organization' => $organization->only(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', [Organization::class, $organization]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('organizations', 'slug')->ignore($organization->id)],
        ]);
        $organization->fill($data)->save();

        return back()->with('success', 'Organization updated.');
    }
}
