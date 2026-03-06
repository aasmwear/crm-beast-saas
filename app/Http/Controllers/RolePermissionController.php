<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
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
     * Store a new role for the current tenant (team-scoped).
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        $name = $request->validated('name');

        $role = Role::create([
            'name' => $name,
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);

        AuditLogger::log(
            $org,
            $user,
            'create',
            'role',
            $role->id,
            ['name' => $name],
        );

        return back()
            ->with('success', 'Role created. Now choose permissions and click Save.')
            ->with('created_role_id', $role->id);
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

        if (! $user->is_super_admin && ! $user->hasPermissionTo('roles.manage')) {
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

        $permissionCatalog = $this->buildPermissionCatalog($allPermissions);

        return Inertia::render($page, array_merge([
            'roles' => $roles,
            'permissions' => $allPermissions,
            'groupedPermissions' => $groupedPermissions,
            'permissionCatalog' => $permissionCatalog,
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

        return back()->with('success', "Permissions saved.");
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

    /**
     * Build the permission catalog for the Matrix UI.
     * Single source of truth: modules, actions, matrix (module->action->permission), specials, aliases.
     *
     * @param  \Illuminate\Support\Collection<int, array{id: int, name: string, module: string}>  $allPermissions
     * @return array{modules: array<int, array{key: string, label: string, icon: string, sortOrder: int}>, actions: array<int, array{key: string, label: string}>, matrix: array<string, array<string, string>>, specials: array<int, array{module: string, permission: string, label: string}>, aliasMap: array<string, string>}
     */
    private function buildPermissionCatalog($allPermissions): array
    {
        $permissionNames = $allPermissions->pluck('name')->toArray();

        $modules = [
            ['key' => 'clients', 'label' => 'Clients', 'icon' => '👥', 'sortOrder' => 1],
            ['key' => 'projects', 'label' => 'Projects', 'icon' => '📁', 'sortOrder' => 2],
            ['key' => 'tasks', 'label' => 'Tasks', 'icon' => '✅', 'sortOrder' => 3],
            ['key' => 'attendance', 'label' => 'Attendance', 'icon' => '⏰', 'sortOrder' => 4],
            ['key' => 'announcements', 'label' => 'Announcements', 'icon' => '📢', 'sortOrder' => 5],
            ['key' => 'notifications', 'label' => 'Notifications', 'icon' => '🔔', 'sortOrder' => 6],
            ['key' => 'activity', 'label' => 'Activity', 'icon' => '📋', 'sortOrder' => 7],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => '⚙️', 'sortOrder' => 8],
            ['key' => 'billing', 'label' => 'Billing', 'icon' => '💳', 'sortOrder' => 9],
            ['key' => 'users', 'label' => 'Users', 'icon' => '👤', 'sortOrder' => 10],
            ['key' => 'departments', 'label' => 'Departments', 'icon' => '🏬', 'sortOrder' => 11],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => '📊', 'sortOrder' => 12],
            ['key' => 'roles', 'label' => 'Roles', 'icon' => '🎭', 'sortOrder' => 13],
            ['key' => 'api_keys', 'label' => 'API Keys', 'icon' => '🔑', 'sortOrder' => 14],
            ['key' => 'financials', 'label' => 'Financials', 'icon' => '💰', 'sortOrder' => 15],
            ['key' => 'contacts', 'label' => 'Contacts', 'icon' => '📇', 'sortOrder' => 16],
            ['key' => 'messages', 'label' => 'Messages', 'icon' => '💬', 'sortOrder' => 17],
        ];

        $actions = [
            ['key' => 'view', 'label' => 'View'],
            ['key' => 'create', 'label' => 'Create'],
            ['key' => 'update', 'label' => 'Update'],
            ['key' => 'delete', 'label' => 'Delete'],
            ['key' => 'manage', 'label' => 'Manage'],
        ];

        // Canonical permission names: *.view, *.create, *.update, *.delete
        $matrixTemplate = [
            'clients' => ['view' => 'clients.view', 'create' => 'clients.create', 'update' => 'clients.update', 'delete' => 'clients.delete', 'manage' => 'clients.manage'],
            'projects' => ['view' => 'projects.view', 'create' => 'projects.create', 'update' => 'projects.update', 'delete' => 'projects.delete'],
            'tasks' => ['view' => 'tasks.view', 'create' => 'tasks.create', 'update' => 'tasks.update', 'delete' => 'tasks.delete'],
            'attendance' => ['view' => 'attendance.view', 'manage' => 'attendance.manage'],
            'announcements' => ['view' => 'announcements.view', 'create' => 'announcements.create', 'update' => 'announcements.update', 'delete' => 'announcements.delete'],
            'notifications' => ['view' => 'notifications.view', 'update' => 'notifications.update'],
            'activity' => ['view' => 'activity.view'],
            'settings' => ['view' => 'settings.view', 'update' => 'settings.update'],
            'billing' => ['view' => 'billing.view', 'manage' => 'billing.manage', 'update' => 'billing.update'],
            'users' => ['view' => 'users.view', 'create' => 'users.create', 'update' => 'users.update', 'delete' => 'users.delete', 'manage' => 'users.manage'],
            'departments' => ['view' => 'departments.view', 'create' => 'departments.create', 'update' => 'departments.update', 'delete' => 'departments.delete'],
            'reports' => ['view' => 'reports.view'],
            'roles' => ['view' => 'roles.view', 'manage' => 'roles.manage'],
            'api_keys' => ['view' => 'api_keys.view', 'create' => 'api_keys.create', 'delete' => 'api_keys.delete'],
            'financials' => ['view' => 'financials.view'],
            'contacts' => ['manage' => 'contacts.manage'],
            'messages' => ['create' => 'messages.create'],
        ];

        $specials = [
            ['module' => 'clients', 'permission' => 'clients.import', 'label' => 'Import'],
            ['module' => 'clients', 'permission' => 'clients.export', 'label' => 'Export'],
            ['module' => 'tasks', 'permission' => 'tasks.review', 'label' => 'Review'],
            ['module' => 'attendance', 'permission' => 'attendance.view-own', 'label' => 'View Own'],
            ['module' => 'attendance', 'permission' => 'attendance.clock-in', 'label' => 'Clock In'],
            ['module' => 'attendance', 'permission' => 'attendance.clock-out', 'label' => 'Clock Out'],
            ['module' => 'attendance', 'permission' => 'attendance.approve', 'label' => 'Approve'],
            ['module' => 'announcements', 'permission' => 'announcements.pin', 'label' => 'Pin'],
            ['module' => 'roles', 'permission' => 'roles.assign', 'label' => 'Assign'],
            ['module' => 'users', 'permission' => 'users.assign-roles', 'label' => 'Assign Roles'],
            ['module' => 'reports', 'permission' => 'reports.export', 'label' => 'Export'],
        ];

        // Legacy -> canonical: when resolving UI display, map legacy to canonical for consistency
        $aliasMap = [
            'clients.edit' => 'clients.update',
            'projects.edit' => 'projects.update',
            'tasks.edit' => 'tasks.update',
            'users.edit' => 'users.update',
        ];

        $matrix = [];
        foreach ($matrixTemplate as $modKey => $actionPerms) {
            $matrix[$modKey] = [];
            foreach ($actionPerms as $actionKey => $permName) {
                if (in_array($permName, $permissionNames, true)) {
                    $matrix[$modKey][$actionKey] = $permName;
                }
            }
        }

        $filteredSpecials = [];
        foreach ($specials as $s) {
            if (in_array($s['permission'], $permissionNames, true)) {
                $filteredSpecials[] = [
                    'module' => $s['module'],
                    'permission' => $s['permission'],
                    'label' => $s['label'],
                ];
            }
        }

        return [
            'modules' => $modules,
            'actions' => $actions,
            'matrix' => $matrix,
            'specials' => $filteredSpecials,
            'aliasMap' => $aliasMap,
        ];
    }
}
