<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        /** @var Organization $organization */
        $organization = $request->route('organization');

        $this->authorize('viewAny', User::class);

        // Set team context for Spatie permissions
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        // Fetch users belonging to this organization
        $users = $organization->users()
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($organization, $currentUser) { // <--- Added $currentUser here
                // Get roles for this specific team/organization
                $teamRoles = $user->roles()
                    ->where(function ($query) use ($organization) {
                        $query->where('roles.team_id', $organization->id) // <--- Fixed Ambiguity
                            ->orWhereNull('roles.team_id');
                    })
                    ->get(['roles.id', 'roles.name', 'roles.team_id']); // <--- Fixed Ambiguity

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => substr($user->name, 0, 2),
                    'is_super_admin' => $user->is_super_admin,
                    'active_organization_id' => $user->active_organization_id,
                    'created_at' => optional($user->created_at)->toIso8601String(),
                    'roles' => $teamRoles->map(fn ($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'color' => $this->getRoleColor($role->name),
                        'is_team_scoped' => $role->team_id !== null,
                    ]),
                    'can_update' => $currentUser->can('update', $user),
                    'can_assign_roles' => $currentUser->can('assignRoles', $user),
                    'can_delete' => $currentUser->can('delete', $user),
                ];
            });

        // Fetch available roles for this organization
        $availableRoles = Role::query()
            ->where(function ($query) use ($organization) {
                $query->where('roles.team_id', $organization->id)
                    ->orWhereNull('roles.team_id');
            })
            ->orderBy('name')
            ->get(['roles.id', 'roles.name', 'roles.team_id']) // <--- Fixed Ambiguity
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'is_team_scoped' => $role->team_id !== null,
                'can_assign' => $this->canAssignRole($currentUser, $role, $organization),
            ]);

        return Inertia::render('Users/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'users' => $users,
            'availableRoles' => $availableRoles,
        ]);
    }

    public function update(UpdateUserRoleRequest $request, Organization $organization, User $user): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (! $user->organizations->contains($organization->id)) {
            abort(403, 'User does not belong to this organization.');
        }

        // Set the global context so Spatie knows what team_id to write to the pivot table
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        $validated = $request->validated();
        $roles = Role::query()->whereIn('id', $validated['role_ids'])->get();

        // Audit Log Prep
        $oldRoles = $user->roles()
            ->where(function ($query) use ($organization) {
                $query->where('roles.team_id', $organization->id)
                    ->orWhereNull('roles.team_id');
            })
            ->pluck('roles.name')
            ->toArray();

        DB::transaction(function () use ($user, $roles, $organization) {
            // 1. Detach all existing roles for THIS organization only
            // We use wherePivot to ensure we don't wipe roles from other orgs
            $user->roles()
                ->wherePivot('team_id', $organization->id)
                ->detach();

            // 2. Assign the new selected roles
            foreach ($roles as $role) {
                // Security: Skip roles that belong to a different team
                if ($role->team_id && (int) $role->team_id !== (int) $organization->id) {
                     continue; 
                }
                
                // FIX: Pass the Model directly. Do NOT pass organization->id as a second arg.
                $user->assignRole($role);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Roles updated for {$user->name}.");
    }

    private function canAssignRole(User $currentUser, Role $role, Organization $organization): bool
    {
        if ($currentUser->is_super_admin) return true;
        if ($role->name === 'super-admin') return false;
        if ($role->team_id !== null && (int) $role->team_id !== (int) $organization->id) return false;

        return $currentUser->can('users.manage'); // Simplified permission check
    }

    private function getRoleColor($roleName)
    {
        return match (strtolower($roleName)) {
            'company admin', 'admin' => 'purple',
            'employee' => 'blue',
            'client' => 'green',
            'super-admin', 'super admin' => 'yellow',
            default => 'gray',
        };
    }
}