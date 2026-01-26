<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $org = $request->route('organization');

        if ($org instanceof Organization && ! Features::enabled($featureKey, $org)) {
            abort(404);
        }

        return $next($request);
    }
}
