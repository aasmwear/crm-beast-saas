<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class RoleAssignmentController extends Controller
{
    public function index(): Response
    {
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/RoleAssignment', ['users' => $users, 'roles' => $roles]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'roles' => ['array'],
            'roles.*' => ['string'],
        ]);

        $user = User::query()->findOrFail($data['user_id']);
        $user->syncRoles($data['roles'] ?? []);

        return back()->with('success', 'Roles updated');
    }
}
