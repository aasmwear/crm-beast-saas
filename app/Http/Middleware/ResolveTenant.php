<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Resolve org from route; accept both bound model and string slug.
        $orgParam = $request->route('organization');

        if ($orgParam instanceof Organization) {
            $org = $orgParam;
        } elseif (is_string($orgParam)) {
            $org = Organization::query()->where('slug', $orgParam)->first();
            if ($org) {
                // Normalize to model so downstream type-hints stay happy.
                $request->route()->setParameter('organization', $org);
            } else {
                // Unknown slug — continue; controller/route model binding can 404 later.
                return $next($request);
            }
        } else {
            // No org in route; nothing to scope.
            return $next($request);
        }

        // Expose organization if you want to resolve it from the container later.
        app()->instance('scoped.organization', $org);

        // Team-aware scoping for Spatie (align; never abort here).
        if (Auth::check()) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);

            $user = $request->user();

            // Align the user's active org to the route's org if schema supports it.
            try {
                if (Schema::hasColumn('users', 'active_organization_id')
                    && (int) $user->active_organization_id !== (int) $org->id) {
                    $user->forceFill(['active_organization_id' => $org->id])->saveQuietly();
                }
            } catch (\Throwable $e) {
                report($e); // never block the request
            }
        }

        return $next($request);
    }
}
