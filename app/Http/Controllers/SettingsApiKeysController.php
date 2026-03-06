<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class SettingsApiKeysController extends Controller
{
    private const PREFIX_LENGTH = 8;

    private const TOKEN_BYTES = 32;

    /**
     * Create a new API key. Returns plaintext token only once.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('api_keys.create'), 403);

        /** @var Organization $organization */
        $organization = $request->route('organization');
        $orgId = (int) $organization->id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $plaintext = 'crmb_' . Str::random(self::TOKEN_BYTES);
        $prefix = substr($plaintext, 0, self::PREFIX_LENGTH);
        $hashedKey = hash('sha256', $plaintext);

        $apiKey = OrganizationApiKey::query()->create([
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'prefix' => $prefix,
            'hashed_key' => $hashedKey,
            'created_by_user_id' => $request->user()?->id,
        ]);

        AuditLogger::log(
            $organization,
            $request->user(),
            'created',
            'api_key',
            $apiKey->id,
            ['prefix' => $prefix, 'name' => $validated['name']],
        );

        return response()->json([
            'success' => true,
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'prefix' => $apiKey->prefix,
                'created_at' => $apiKey->created_at->toIso8601String(),
            ],
            'plaintext_token' => $plaintext,
        ]);
    }

    /**
     * Revoke an API key (soft delete via revoked_at).
     * Scoped binding ensures apiKey belongs to organization; 404 if not.
     */
    public function destroy(Request $request, Organization $organization, OrganizationApiKey $apiKey): JsonResponse
    {
        abort_unless($request->user()?->can('api_keys.delete'), 403);

        if ($apiKey->revoked_at !== null) {
            return response()->json(['success' => true, 'message' => 'Key already revoked.']);
        }

        $apiKey->revoked_at = now();
        $apiKey->save();

        AuditLogger::log(
            $organization,
            $request->user(),
            'revoked',
            'api_key',
            $apiKey->id,
            ['prefix' => $apiKey->prefix],
        );

        return response()->json(['success' => true, 'message' => 'API key revoked.']);
    }
}
