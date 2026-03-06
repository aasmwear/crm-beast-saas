<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\OrganizationApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiKeyAuthTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private string $validToken;

    private OrganizationApiKey $apiKeyA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['slug' => 'org-a', 'name' => 'Org A']);
        $this->orgB = Organization::factory()->create(['slug' => 'org-b', 'name' => 'Org B']);

        $this->validToken = 'crmb_' . bin2hex(random_bytes(16));
        $this->apiKeyA = OrganizationApiKey::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Org A key',
            'prefix' => substr($this->validToken, 0, 8),
            'hashed_key' => hash('sha256', $this->validToken),
            'created_by_user_id' => null,
        ]);
    }

    public function test_401_when_authorization_header_missing(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized']);
        $this->assertStringNotContainsString('crmb_', $response->getContent());
    }

    public function test_401_when_bearer_token_malformed(): void
    {
        $response = $this->getJson('/api/ping', [
            'Authorization' => 'Basic abc123',
        ]);

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_401_when_bearer_token_empty(): void
    {
        $response = $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ',
        ]);

        $response->assertUnauthorized();
    }

    public function test_401_when_token_invalid_prefix(): void
    {
        $response = $this->getJson('/api/ping', [
            'Authorization' => 'Bearer invalid_xxx',
        ]);

        $response->assertUnauthorized();
    }

    public function test_401_when_token_wrong_hash(): void
    {
        $wrongToken = 'crmb_' . str_repeat('a', 32);
        OrganizationApiKey::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Other',
            'prefix' => substr($wrongToken, 0, 8),
            'hashed_key' => hash('sha256', 'different_secret'),
            'created_by_user_id' => null,
        ]);

        $response = $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $wrongToken,
        ]);

        $response->assertUnauthorized();
    }

    public function test_401_when_key_revoked(): void
    {
        $this->apiKeyA->update(['revoked_at' => now()]);

        $response = $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertUnauthorized();
    }

    public function test_200_when_token_valid_returns_org_info(): void
    {
        $response = $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'organization' => [
                    'id' => $this->orgA->id,
                    'slug' => 'org-a',
                    'name' => 'Org A',
                ],
                'api_key_prefix' => $this->apiKeyA->prefix,
            ])
            ->assertJsonStructure([
                'ok',
                'organization' => ['id', 'slug', 'name'],
                'api_key_prefix',
                'timestamp',
            ]);

        $content = $response->getContent();
        $this->assertStringNotContainsString($this->validToken, (string) $content);
    }

    public function test_org_b_cannot_be_returned_with_org_a_key(): void
    {
        $response = $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ]);

        $response->assertOk();
        $org = $response->json('organization');
        $this->assertSame($this->orgA->id, $org['id']);
        $this->assertSame('org-a', $org['slug']);
        $this->assertNotSame($this->orgB->id, $org['id']);
    }

    public function test_last_used_at_updated_on_first_use(): void
    {
        $this->assertNull($this->apiKeyA->last_used_at);

        $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ])->assertOk();

        $this->apiKeyA->refresh();
        $this->assertNotNull($this->apiKeyA->last_used_at);
    }

    public function test_last_used_at_not_updated_within_throttle_window(): void
    {
        $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ])->assertOk();

        $this->apiKeyA->refresh();
        $firstUsed = $this->apiKeyA->last_used_at;
        $this->assertNotNull($firstUsed);

        $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ])->assertOk();

        $this->apiKeyA->refresh();
        $this->assertTrue($this->apiKeyA->last_used_at->eq($firstUsed));
    }

    public function test_last_used_at_updated_after_throttle_window(): void
    {
        $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ])->assertOk();

        $this->apiKeyA->refresh();
        $firstUsed = $this->apiKeyA->last_used_at->copy();

        $this->travel(6)->minutes();

        $this->getJson('/api/ping', [
            'Authorization' => 'Bearer ' . $this->validToken,
        ])->assertOk();

        $this->apiKeyA->refresh();
        $this->assertTrue($this->apiKeyA->last_used_at->gt($firstUsed));
    }
}
