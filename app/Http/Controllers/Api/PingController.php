<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\Organization $organization */
        $organization = $request->attributes->get('organization');
        /** @var \App\Models\OrganizationApiKey $apiKey */
        $apiKey = $request->attributes->get('org_api_key');

        return response()->json([
            'ok' => true,
            'organization' => [
                'id' => $organization->id,
                'slug' => $organization->slug,
                'name' => $organization->name,
            ],
            'api_key_prefix' => $apiKey->prefix,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
