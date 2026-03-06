<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\OrganizationApiKey;
use App\Models\Platform\OrganizationFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiRpmEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_specific_api_rpm_entitlement_changes_rate_limit_behavior(): void
    {
        [$orgA, $tokenA] = $this->createOrgWithApiRpm('alpha', 2);
        [$orgB, $tokenB] = $this->createOrgWithApiRpm('beta', 4);

        // Org A: 2 requests allowed, 3rd blocked.
        $this->getJson('/api/ping', ['Authorization' => 'Bearer '.$tokenA])->assertOk();
        $this->getJson('/api/ping', ['Authorization' => 'Bearer '.$tokenA])->assertOk();
        $this->getJson('/api/ping', ['Authorization' => 'Bearer '.$tokenA])
            ->assertStatus(429)
            ->assertJson(['code' => 'rate_limited']);

        // Org B has independent higher limit.
        $this->getJson('/api/ping', ['Authorization' => 'Bearer '.$tokenB])->assertOk();
        $this->getJson('/api/ping', ['Authorization' => 'Bearer '.$tokenB])->assertOk();
        $this->getJson('/api/ping', ['Authorization' => 'Bearer '.$tokenB])->assertOk();
        $this->getJson('/api/ping', ['Authorization' => 'Bearer '.$tokenB])->assertOk();
        $this->getJson('/api/ping', ['Authorization' => 'Bearer '.$tokenB])
            ->assertStatus(429)
            ->assertJson(['code' => 'rate_limited']);

        $this->assertNotSame($orgA->id, $orgB->id);
    }

    /**
     * @return array{0: Organization, 1: string}
     */
    private function createOrgWithApiRpm(string $slug, int $apiRpm): array
    {
        $org = Organization::factory()->create(['slug' => $slug]);
        $token = 'crmb_'.bin2hex(random_bytes(16));

        OrganizationApiKey::query()->create([
            'organization_id' => $org->id,
            'name' => 'test key',
            'prefix' => substr($token, 0, 8),
            'hashed_key' => hash('sha256', $token),
            'created_by_user_id' => null,
        ]);

        OrganizationFeature::query()->create([
            'organization_id' => $org->id,
            'features' => [
                'attendance' => true,
                'sms' => false,
                'api_access' => true,
                'storage_gb' => 5,
                'api_rpm' => $apiRpm,
                'exports_per_day' => 5,
            ],
            'subscription_status' => 'active',
        ]);

        return [$org, $token];
    }
}
