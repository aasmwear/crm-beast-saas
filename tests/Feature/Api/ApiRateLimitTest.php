<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\Platform\OrganizationFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private string $validToken;

    protected function setUp(): void
    {
        parent::setUp();

        $slug = 'org-' . uniqid((string) mt_rand(), true);
        $this->org = Organization::factory()->create(['slug' => $slug, 'name' => 'Test Org']);
        $this->validToken = 'crmb_' . bin2hex(random_bytes(16));
        OrganizationApiKey::create([
            'organization_id' => $this->org->id,
            'name' => 'Test key',
            'prefix' => substr($this->validToken, 0, 8),
            'hashed_key' => hash('sha256', $this->validToken),
            'created_by_user_id' => null,
        ]);

        OrganizationFeature::query()->create([
            'organization_id' => $this->org->id,
            'features' => [
                'attendance' => true,
                'sms' => false,
                'api_access' => true,
                'storage_gb' => 5,
                'api_rpm' => 3,
                'exports_per_day' => 5,
            ],
            'subscription_status' => 'active',
        ]);
    }

    public function test_can_call_ping_within_limit(): void
    {
        $response1 = $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);
        $response2 = $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response1->assertOk()->assertJson(['ok' => true]);
        $response2->assertOk()->assertJson(['ok' => true]);
    }

    public function test_exceed_limit_returns_429_with_rate_limited_code(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/ping', [
                'Authorization' => 'Bearer ' . $this->validToken,
            ])->assertOk();
        }

        $response = $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'message' => 'Too many requests.',
                'code' => 'rate_limited',
            ]);
    }
}
