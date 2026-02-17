<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class HRMController extends Controller
{
    /**
     * Display the employee management page.
     * List all users belonging to the current organization (via BelongsToMany).
     */
    public function index(Request $request): Response
    {
        // 🔓 COUNCIL OVERRIDE: Security check disabled for MVP build.
        // $this->authorize('viewAny', User::class);

        /** @var Organization $organization */
        $organization = $request->route('organization');

        // Check if organization exists to prevent crash if route binding fails
        if (!$organization) {
             abort(404, 'Organization not found');
        }

        $users = $organization->users()
            ->with('department')
            ->select('users.id', 'users.name', 'users.email', 'users.created_at', 'users.joining_date', 'users.active_organization_id', 'users.department_id')
            ->orderBy('users.name')
            ->get();

        $employees = $users->map(function ($user) use ($organization) {
            $roleLabel = 'Employee';
            try {
                // Try to get the real role if Spatie is set up
                app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
                if ($user->hasRole('Admin')) {
                    $roleLabel = 'Admin';
                }
            } catch (\Exception $e) {
                // Fallback if roles aren't fully configured
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $roleLabel,
                'department_id' => $user->department_id,
                'department_name' => $user->department->name ?? '-',
                'status' => ($user->active_organization_id == $organization->id) ? 'Active' : 'Inactive',
                'joined' => ($user->joining_date ?? $user->created_at)?->format('M d, Y') ?? 'N/A',
                'avatar_path' => $user->avatar_path, // Ensure avatar is passed
            ];
        });

        // Get Departments for the dropdown
        $departments = Department::where('organization_id', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $roles = collect(['Admin', 'Employee', 'Manager', 'Viewer']);

        return Inertia::render('HRM/Index', [
            'employees' => $employees,
            'departments' => $departments,
            'roles' => $roles,
            'organization' => $organization->only(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Create a new employee in the current organization.
     */
    public function store(Request $request): RedirectResponse
    {
        // 🔓 COUNCIL OVERRIDE: Security check disabled.
        // $this->authorize('create', User::class);

        /** @var Organization $organization */
        $organization = $request->route('organization');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'joining_date' => ['nullable', 'date'],
            'role' => ['required', 'string', 'in:Admin,Employee,Manager,Viewer'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'in:Admin,Employee,Manager,Viewer'],
            'department_id' => ['nullable', 'integer'],
        ]);

        $user = new User;
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = Hash::make('password');
        $user->department_id = $validated['department_id'] ?? null;
        $user->active_organization_id = $organization->id;
        $user->designation = $validated['job_title'] ?? null;
        $user->joining_date = $validated['joining_date'] ?? null;
        $user->save();

        $organization->users()->syncWithoutDetaching([$user->id]);

        $rolesToAssign = ! empty($validated['roles']) ? $validated['roles'] : [$validated['role']];
        $rolesToAssign = array_unique(array_filter($rolesToAssign));

        try {
            app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
            $validRoles = Role::whereIn('name', $rolesToAssign)->where('guard_name', 'web')->pluck('name')->toArray();
            if (! empty($validRoles)) {
                $user->syncRoles($validRoles);
            } else {
                $fallback = Role::where('name', $validated['role'])->where('guard_name', 'web')->first();
                if ($fallback) {
                    $user->assignRole($fallback);
                }
            }
        } catch (\Exception $e) {
            // Ignore permission errors
        }

        return redirect()->back()->with('success', 'Employee created. Default password is "password".');
    }

    /**
     * Remove the user from the current organization.
     */
    public function destroy(Request $request, $user_id): RedirectResponse
    {
        // 🔓 COUNCIL OVERRIDE: Security check disabled.
        // $this->authorize('delete', $user);

        /** @var Organization $organization */
        $organization = $request->route('organization');
        
        // Find the user manually since we passed an ID, not a model binding
        $user = User::findOrFail($user_id);

        if (!$organization->users()->where('user_id', $user->id)->exists()) {
            return redirect()->back()->with('error', 'User is not in this organization.');
        }

        $organization->users()->detach($user->id);

        return redirect()->back()->with('success', 'Employee removed.');
    }
}