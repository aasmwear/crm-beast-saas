<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\PermissionRegistrar;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     * In testing, return null to disable version conflict checks (avoids 409).
     */
    public function version(Request $request): ?string
    {
        if (app()->environment('testing')) {
            return null;
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $organization = null;
        if ($user && $user->active_organization_id) {
            $org = Organization::find($user->active_organization_id);
            if ($org) {
                $organization = [
                    'name' => $org->name,
                    'logo_path' => $org->logo_path,
                    'timezone' => $org->timezone ?? 'UTC',
                ];
            }
        }

        $isAdmin = $this->resolveIsAdmin($request, $user);

        return array_merge(parent::share($request), [
            'app' => [
                'name' => config('app.name'),
                'env' => app()->environment(),
                'url' => config('app.url'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_path' => $user->avatar_path,
                    'is_super_admin' => (bool) ($user->is_super_admin ?? false),
                    'active_organization_id' => $user->active_organization_id,
                ] : null,

                'is_admin' => $isAdmin,
            ],
            'organization' => $organization,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'created_role_id' => fn () => $request->session()->get('created_role_id'),
            ],
        ]);
    }

    /**
     * Resolve whether the current user is an admin (sees full dashboard, financial KPIs).
     * Employees and Clients are non-admin.
     */
    private function resolveIsAdmin(Request $request, $user): bool
    {
        if (! $user) {
            return false;
        }

        // Super Admins (PlatformAdmin) are always admins and do not use Spatie.
        if ($user instanceof \App\Models\Platform\PlatformAdmin) {
            return true;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        // Only call hasAnyRole on models that support it (User).
        if (! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        $org = $request->route('organization');
        $orgId = null;
        if ($org instanceof Organization) {
            $orgId = (int) $org->id;
        } elseif ($org && is_object($org) && isset($org->id)) {
            $orgId = (int) $org->id;
        } elseif (isset($user->active_organization_id) && $user->active_organization_id) {
            $orgId = (int) $user->active_organization_id;
        }

        if ($orgId) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($orgId);
        }

        return (bool) $user->hasAnyRole(['Super Admin', 'Manager', 'Owner', 'Admin']);
    }
}
