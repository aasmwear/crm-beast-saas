<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RolePermissionController extends Controller
{
    public function editor(Request $request): Response
    {
        if (! $request->user()->hasRole('Admin')) {
            abort(403);
        }

        return Inertia::render('Settings/RoleEditor', [
            'roles' => Role::all(['id', 'name']),
            'permissions' => Permission::all(['id', 'name']),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        if (! $request->user()->hasRole('Admin')) {
            abort(403);
        }

        /** @var \App\Models\Organization $org */
        $org = $request->route('organization');

        $data = $request->validate([
            'role_id' => 'required|integer',
            'permissions_map' => 'required|array',
        ]);

        Setting::put(
            (int) $org->id,
            "role:{$data['role_id']}:permissions_map",
            $data['permissions_map'],
        );

        AuditLogger::log(
            $org,
            $request->user(),
            'update',
            'role_permissions',
            (int) $data['role_id'],
            $data['permissions_map'],
        );

        return back()->with('success', 'Role permissions updated');
    }
}
