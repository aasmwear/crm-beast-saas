<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class PermissionEditorController extends Controller
{
    public function index(Request $request, string $organization): Response
    {
        // Admin gate at route/middleware layer or here as needed
        $user = $request->user();
        if (! $user || ! $user->hasRole('Admin')) {
            abort(403);
        }

        // Use query builder to avoid PHPStan property warnings on Role model
        $roles = DB::table('roles')->select(['id', 'name', 'permissions_map'])->orderBy('name')->get();

        return Inertia::render('Admin/Permissions', [
            'roles' => $roles->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'name' => (string) $r->name,
                    'permissions_map' => $r->permissions_map ? json_decode($r->permissions_map, true) : new \stdClass,
                ];
            })->all(),
            'entities' => ['users', 'departments', 'clients', 'projects', 'tasks', 'announcements', 'attendance', 'notifications', 'audit_logs', 'settings'],
        ]);
    }

    public function save(Request $request, string $organization): Response
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('Admin')) {
            abort(403);
        }

        $data = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*.id' => ['required', 'integer'],
            'roles.*.permissions_map' => ['required', 'array'],
        ]);

        foreach ($data['roles'] as $row) {
            DB::table('roles')
                ->where('id', (int) $row['id'])
                ->update(['permissions_map' => json_encode($row['permissions_map'])]);
        }

        return Inertia::render('Admin/Permissions', [
            'roles' => DB::table('roles')->select(['id', 'name', 'permissions_map'])->orderBy('name')->get()->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'name' => (string) $r->name,
                    'permissions_map' => $r->permissions_map ? json_decode($r->permissions_map, true) : new \stdClass,
                ];
            })->all(),
            'entities' => ['users', 'departments', 'clients', 'projects', 'tasks', 'announcements', 'attendance', 'notifications', 'audit_logs', 'settings'],
        ]);
    }
}
