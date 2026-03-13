<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\SeatCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class HRMController extends Controller
{
    /**
     * Global roles that are intentionally assignable from tenant HRM flows.
     *
     * @var array<int, string>
     */
    private const ASSIGNABLE_GLOBAL_ROLE_NAMES = [
        'Owner',
        'Manager',
        'Employee',
        'Client',
    ];

    /**
     * Display the employee management page.
     * List all users belonging to the current organization (via BelongsToMany).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        /** @var Organization $organization */
        $organization = $request->route('organization');

        // Check if organization exists to prevent crash if route binding fails
        if (!$organization) {
             abort(404, 'Organization not found');
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));

        $users = $organization->users()
            ->with(['department', 'roles'])
            ->select('users.id', 'users.name', 'users.email', 'users.designation', 'users.created_at', 'users.joining_date', 'users.active_organization_id', 'users.department_id')
            ->orderBy('users.name')
            ->when($search !== '', static function ($query) use ($search): void {
                $query->where(static function ($where) use ($search): void {
                    $where
                        ->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('users.designation', 'like', "%{$search}%")
                        ->orWhereHas('department', static function ($departmentQuery) use ($search): void {
                            $departmentQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->paginate(25)
            ->withQueryString();

        $employees = $users->through(function (User $user) use ($organization): array {
            $roleLabel = $user->roles->pluck('name')->join(', ') ?: 'Employee';

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
                'role' => $roleLabel,
                'department_id' => $user->department_id,
                'department_name' => $user->department->name ?? '-',
                'status' => ((int) $user->active_organization_id === (int) $organization->id) ? 'Active' : 'Inactive',
                'joined' => ($user->joining_date ?? $user->created_at)?->format('M d, Y') ?? 'N/A',
                'avatar_path' => $user->avatar_path,
            ];
        });

        // Get Departments for the dropdown
        $departments = Department::where('organization_id', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $roles = Role::where(function ($query) use ($organization): void {
            $query->where('team_id', $organization->id)
                ->orWhere(function ($globalScope): void {
                    $globalScope->whereNull('team_id')
                        ->whereIn('name', self::ASSIGNABLE_GLOBAL_ROLE_NAMES);
                });
        })
            ->orderBy('name')
            ->pluck('name');

        $user = $request->user();
        $canCreate = $user && ($user->can('hrm.create') || $user->can('hrm.manage') || $user->can('users.create') || $user->can('users.manage'));
        $canEdit = $user && ($user->can('hrm.edit') || $user->can('hrm.manage') || $user->can('users.update') || $user->can('users.manage'));
        $canDelete = $user && ($user->can('hrm.delete') || $user->can('hrm.manage') || $user->can('users.delete'));

        return Inertia::render('HRM/Index', [
            'employees' => $employees,
            'filters' => [
                'q' => $search,
            ],
            'departments' => $departments,
            'roles' => $roles,
            'organization' => $organization->only(['id', 'name', 'slug']),
            'canCreate' => $canCreate,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
        ]);
    }

    /**
     * Create a new employee in the current organization.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        /** @var Organization $organization */
        $organization = $request->route('organization');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'joining_date' => ['nullable', 'date'],
            'role' => ['required', 'string', $this->assignableRoleRule((int) $organization->id)],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', $this->assignableRoleRule((int) $organization->id)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('organization_id', $organization->id)],
        ]);

        app(SeatCounter::class)->assertCanAddSeat($organization);

        $rolesToAssign = ! empty($validated['roles']) ? $validated['roles'] : [$validated['role']];
        $rolesToAssign = array_unique(array_filter($rolesToAssign));
        $createdUserId = null;

        try {
            /** @var User $user */
            $user = DB::transaction(function () use ($validated, $organization, $rolesToAssign): User {
                $user = new User;
                $user->name = $validated['name'];
                $user->email = $validated['email'];
                // Never use a predictable default password.
                $user->password = Hash::make(Str::password(32));
                $user->department_id = $validated['department_id'] ?? null;
                $user->active_organization_id = $organization->id;
                $user->designation = $validated['job_title'] ?? null;
                $user->joining_date = $validated['joining_date'] ?? null;
                $user->save();

                $organization->users()->syncWithoutDetaching([$user->id]);

                app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
                $validRoles = $this->resolveAssignableRoleNames((int) $organization->id, $rolesToAssign);
                if ($validRoles === []) {
                    throw ValidationException::withMessages([
                        'role' => ['No assignable role was resolved for this employee.'],
                    ]);
                }
                $user->syncRoles($validRoles);

                return $user;
            });

            $createdUserId = (int) $user->id;
        } catch (\Throwable $e) {
            Log::error('HRM employee creation failed during role assignment', [
                'organization_id' => (int) $organization->id,
                'user_id' => $createdUserId,
                'attempted_role' => implode(',', $rolesToAssign),
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            if ($e instanceof ValidationException) {
                throw $e;
            }

            return redirect()->back()
                ->withErrors(['hrm' => 'Employee could not be created. Please try again.'])
                ->withInput();
        }

        try {
            Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            Log::warning('HRM employee created but password setup link could not be sent', [
                'organization_id' => (int) $organization->id,
                'user_id' => (int) $user->id,
                'attempted_role' => implode(',', $rolesToAssign),
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
        }

        return redirect()->back()->with('success', 'Employee created. Password setup email has been queued.');
    }

    /**
     * Update an employee's profile and role in the current organization.
     */
    public function update(Request $request, Organization $organization, User $user): RedirectResponse
    {
        if (! $organization->users()->where('user_id', $user->id)->exists()) {
            abort(404, 'User is not in this organization.');
        }

        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'designation' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('organization_id', (int) $organization->id)],
            'role' => ['required', 'string', $this->assignableRoleRule((int) $organization->id)],
        ]);

        try {
            DB::transaction(function () use ($user, $validated, $organization): void {
                $user->name = $validated['name'];
                $user->email = $validated['email'];
                $user->designation = $validated['designation'] ?? null;
                $user->department_id = $validated['department_id'] ?? null;
                $user->save();

                app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
                $roleNames = $this->resolveAssignableRoleNames((int) $organization->id, [(string) $validated['role']]);
                if ($roleNames === []) {
                    throw ValidationException::withMessages([
                        'role' => ['The selected role is not assignable in this organization.'],
                    ]);
                }
                $user->syncRoles($roleNames);
            });
        } catch (\Throwable $e) {
            Log::error('HRM employee update failed during role assignment', [
                'organization_id' => (int) $organization->id,
                'user_id' => (int) $user->id,
                'attempted_role' => (string) $validated['role'],
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            if ($e instanceof ValidationException) {
                throw $e;
            }

            return redirect()->back()
                ->withErrors(['hrm' => 'Employee could not be updated. Please try again.'])
                ->withInput();
        }

        return redirect()->back()->with('success', 'Employee updated.');
    }

    /**
     * Remove the user from the current organization.
     */
    public function destroy(Request $request, Organization $organization, User $user): RedirectResponse
    {
        if (! $organization->users()->where('user_id', $user->id)->exists()) {
            abort(404, 'User is not in this organization.');
        }

        $this->authorize('delete', $user);

        $organization->users()->detach($user->id);

        return redirect()->back()->with('success', 'Employee removed.');
    }

    private function assignableRoleRule(int $organizationId): Exists
    {
        return Rule::exists('roles', 'name')->where(function ($query) use ($organizationId): void {
            $query->where('guard_name', 'web')
                ->where(function ($roleScope) use ($organizationId): void {
                    $roleScope->where('team_id', $organizationId)
                        ->orWhere(function ($globalScope): void {
                            $globalScope->whereNull('team_id')
                                ->whereIn('name', self::ASSIGNABLE_GLOBAL_ROLE_NAMES);
                        });
                });
        });
    }

    /**
     * @param  array<int, string>  $roleNames
     * @return array<int, string>
     */
    private function resolveAssignableRoleNames(int $organizationId, array $roleNames): array
    {
        if ($roleNames === []) {
            return [];
        }

        return Role::query()
            ->whereIn('name', $roleNames)
            ->where('guard_name', 'web')
            ->where(function ($query) use ($organizationId): void {
                $query->where('team_id', $organizationId)
                    ->orWhere(function ($globalScope): void {
                        $globalScope->whereNull('team_id')
                            ->whereIn('name', self::ASSIGNABLE_GLOBAL_ROLE_NAMES);
                    });
            })
            ->pluck('name')
            ->toArray();
    }
}