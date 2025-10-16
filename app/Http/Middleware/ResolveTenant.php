<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Accept either {org} or {organization} from routes
        $param = $request->route('org') ?? $request->route('organization');

        // If it's already an Organization model, use it
        if ($param instanceof Organization) {
            app()->instance('tenant', $param);

            return $next($request);
        }

        // Otherwise treat as slug (string) and resolve
        if (is_string($param) && $param !== '') {
            $org = Organization::where('slug', $param)->first();
            if ($org) {
                app()->instance('tenant', $org);

                // normalize the bound param so downstream code can type-hint Organization
                $request->route()->setParameter('organization', $org);
            }
        }

        return $next($request);
    }
}
