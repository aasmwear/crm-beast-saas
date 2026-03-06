<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\OrganizationApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates Public API requests via Authorization: Bearer crmb_xxx.
 * Validates token against organization_api_keys (prefix + sha256). Sets tenant context.
 * Does not depend on web session.
 */
final class AuthenticateOrganizationApiKey
{
    private const PREFIX_LENGTH = 8;

    private const LAST_USED_THROTTLE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null || ! $this->hasValidFormat($token)) {
            return $this->unauthorized();
        }

        $prefix = substr($token, 0, self::PREFIX_LENGTH);
        $hashed = hash('sha256', $token);

        $apiKey = OrganizationApiKey::query()
            ->where('prefix', $prefix)
            ->whereNull('revoked_at')
            ->with('organization')
            ->first();

        if ($apiKey === null || ! hash_equals($apiKey->hashed_key, $hashed)) {
            return $this->unauthorized();
        }

        /** @var Organization $organization */
        $organization = $apiKey->organization;

        app()->instance('scoped.organization', $organization);
        $request->attributes->set('org_api_key', $apiKey);
        $request->attributes->set('organization', $organization);

        $this->maybeUpdateLastUsed($apiKey);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if ($header === null || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));
        return $token !== '' ? $token : null;
    }

    private function hasValidFormat(string $token): bool
    {
        return str_starts_with($token, 'crmb_') && strlen($token) >= self::PREFIX_LENGTH;
    }

    private function unauthorized(): Response
    {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    private function maybeUpdateLastUsed(OrganizationApiKey $apiKey): void
    {
        $cutoff = now()->subMinutes(self::LAST_USED_THROTTLE_MINUTES);
        if ($apiKey->last_used_at !== null && $apiKey->last_used_at->gt($cutoff)) {
            return;
        }

        $apiKey->update(['last_used_at' => now()]);
    }
}
