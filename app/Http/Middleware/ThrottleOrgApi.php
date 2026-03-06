<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\Billing\EntitlementsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limits Public API requests by org + api_key + IP.
 * Uses same key logic as org-api limiter; applies limit and returns standardized 429.
 */
final class ThrottleOrgApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $org = app()->bound('scoped.organization') ? app('scoped.organization') : null;
        $apiKey = $request->attributes->get('org_api_key');
        $orgId = $org?->id ?? 'x';
        $keyId = $apiKey?->id ?? 'x';
        $ip = $request->ip() ?? '0';
        $key = "org:{$orgId}:key:{$keyId}:ip:{$ip}";

        $maxAttempts = (int) config('api.rate_limit_per_minute', 60);
        if ($org instanceof Organization) {
            try {
                $entitledRpm = app(EntitlementsService::class)->value('api_rpm', $org);
                if (is_numeric($entitledRpm) && (int) $entitledRpm > 0) {
                    $maxAttempts = (int) $entitledRpm;
                }
            } catch (\Throwable) {
                // Keep API online with config fallback if entitlement lookup fails.
            }
        }
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'Too many requests.',
                'code' => 'rate_limited',
            ], 429);
        }

        RateLimiter::hit($key, $decaySeconds);

        return $next($request);
    }
}
