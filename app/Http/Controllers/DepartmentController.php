<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        $departments = Department::where('organization_id', (int) $org->id)->get();

        return Inertia::render('Departments/Index', ['departments' => $departments]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Department::class);

        /** @var \App\Models\Organization|null $org */
        $org = $request->route('organization');
        abort_if(! $org, 404, 'Organization not resolved');

        // Schema-aware rules (your table has no 'description')
        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20',
        ];
        $validated = $request->validate($rules);

        Department::create([
            'organization_id' => $org->id,
            'name' => $validated['name'],
            'code' => $validated['code'],
        ]);

        return back()->with('success', 'Department created');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        // Schema-aware rules (no 'description' in your DB)
        $rules = [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:20',
        ];
        $validated = $request->validate($rules);

        $department->update($validated);

        return back()->with('success', 'Department updated');
    }
}
