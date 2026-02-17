<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsMatrixRequest;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionController extends Controller
{
    /**
     * Return roles and permission matrix for the matrix grid UI (Settings/Roles.vue).
     */
    public function index(Request $request): Response
    {
        return $this->rolesAndMatrixResponse($request, 'Settings/Roles');
    }

    /**
     * Display the role/permission editor for the current tenant (per-role view).
     */
    public function editor(Request $request): Response
    {
        return $this->rolesAndMatrixResponse($request, 'Settings/RoleEditor');
    }

    /**
     * Build roles + permissions matrix payload. Shared by index() and editor().
     *
     * @param  array<string, mixed>  $extra
     */
    private function rolesAndMatrixResponse(Request $request, string $page, array $extra = []): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        if (! $user->is_super_admin && ! $user->hasPermissionTo('roles.manage', $org->id)) {
            abort(403, 'You do not have permission to manage roles.');
        }

        $roles = Role::query()
            ->where('team_id', $org->id)
            ->orWhereNull('team_id')
            ->orderBy('name')
            ->get(['id', 'name', 'team_id'])
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'team_id' => $role->team_id,
                'is_team_scoped' => $role->team_id !== null,
                'permission_ids' => $role->permissions()->pluck('permissions.id')->toArray(),
            ]);

        $allPermissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($perm) => [
                'id' => $perm->id,
                'name' => $perm->name,
                'module' => $this->extractModule($perm->name),
            ]);

        $groupedPermissions = $allPermissions->groupBy('module')->map(fn ($perms) => $perms->values());

        return Inertia::render($page, array_merge([
            'roles' => $roles,
            'permissions' => $allPermissions,
            'groupedPermissions' => $groupedPermissions,
        ], $extra));
    }

    /**
     * Save role permissions (team-scoped via Spatie).
     */
    public function save(UpdateRolePermissionsRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        $validated = $request->validated();

        /** @var Role $role */
        $role = Role::findOrFail($validated['role_id']);

        // Sync permissions to the role
        $permissions = Permission::query()->whereIn('id', $validated['permission_ids'])->get();
        $role->syncPermissions($permissions);

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forget('spatie.permission.cache');

        // Log the action
        AuditLogger::log(
            $org,
            $user,
            'update',
            'role_permissions',
            $role->id,
            ['permission_ids' => $validated['permission_ids']],
        );

        return back()->with('success', "Permissions for role '{$role->name}' updated successfully.");
    }

    /**
     * Update role permissions from matrix: { role_id: [permission_ids] }.
     */
    public function update(UpdateRolePermissionsMatrixRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        $matrix = $request->validated('matrix', []);

        foreach ($matrix as $roleId => $permissionIds) {
            $role = Role::find((int) $roleId);
            if (! $role) {
                continue;
            }
            $permissionIds = array_values(array_unique((array) $permissionIds));
            $permissions = Permission::query()->whereIn('id', $permissionIds)->get();
            $role->syncPermissions($permissions);
            AuditLogger::log(
                $org,
                $user,
                'update',
                'role_permissions',
                $role->id,
                ['permission_ids' => $permissionIds],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forget('spatie.permission.cache');

        return back()->with('success', 'Role permissions updated successfully.');
    }

    /**
     * Extract module name from permission name (e.g., 'clients.view' -> 'clients', 'view_financials' -> 'view_financials').
     */
    private function extractModule(string $permissionName): string
    {
        $parts = explode('.', $permissionName);

        return $parts[0] ?? 'other';
    }
}
