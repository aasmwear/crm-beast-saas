<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlan
{
    /**
     * Ensure the current organization meets required plan.
     *
     * Usage: ->middleware('ensurePlan:pro') or without param uses config('plan.default')
     */
    public function handle(Request $request, Closure $next, ?string $requiredPlan = null): Response
    {
        /** @var Organization|null $org */
        $org = $request->route('organization');

        if (! $org instanceof Organization) {
            // No org in route – nothing to enforce here
            return $next($request);
        }

        $required = $requiredPlan ?: (string) config('plan.default', 'pro');

        // If your Organization has a 'plan' column:
        $orgPlan = (string) ($org->plan ?? 'trial');

        // Allow if matches or trial; otherwise redirect to billing portal
        if ($orgPlan === $required || $orgPlan === 'trial') {
            return $next($request);
        }

        // Fallback: send to billing portal (if route exists) or dashboard
        if (function_exists('route') && app('router')->has('billing.portal')) {
            return redirect()->route('billing.portal');
        }

        return redirect()->route('dashboard');
    }
}
