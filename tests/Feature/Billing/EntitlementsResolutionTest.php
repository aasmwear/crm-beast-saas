<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\OrganizationSubscription;
use App\Models\Platform\OrganizationFeature;
use App\Services\Billing\EntitlementsService;
use App\Support\PlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementsResolutionTest extends TestCase
{
    use RefreshDatabase;

    private EntitlementsService $entitlements;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entitlements = app(EntitlementsService::class);
    }

    public function test_uses_plan_defaults_when_no_overrides(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 5,
        ]);

        $resolved = $this->entitlements->forOrg($org);

        $this->assertTrue($resolved['attendance'] ?? false);
        $this->assertFalse($resolved['sms'] ?? true);
        $this->assertFalse($resolved['api_access'] ?? true);
        $this->assertSame(5, $resolved['storage_gb'] ?? 0);
        $this->assertSame(60, $resolved['api_rpm'] ?? 0);
    }

    public function test_uses_starter_when_no_subscription_record(): void
    {
        $org = Organization::factory()->create(['slug' => 'no-sub']);

        $resolved = $this->entitlements->forOrg($org);

        $this->assertSame(PlanCatalog::get(PlanCatalog::PLAN_STARTER)['entitlements']['storage_gb'], $resolved['storage_gb'] ?? null);
        $this->assertFalse($resolved['api_access'] ?? true);
    }

    public function test_org_overrides_organization_features_take_precedence(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 5,
        ]);
        OrganizationFeature::query()->create([
            'organization_id' => $org->id,
            'features' => [
                'attendance' => true,
                'sms' => true,
                'api_access' => true,
                'storage_gb' => 20,
                'api_rpm' => 120,
            ],
            'subscription_status' => 'active',
        ]);

        $resolved = $this->entitlements->forOrg($org);

        $this->assertTrue($resolved['sms']);
        $this->assertTrue($resolved['api_access']);
        $this->assertSame(20, $resolved['storage_gb']);
        $this->assertSame(120, $resolved['api_rpm']);
    }

    public function test_addons_augment_numeric_entitlements(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 5,
        ]);
        OrganizationAddon::query()->create([
            'organization_id' => $org->id,
            'addon_key' => 'storage_gb',
            'quantity' => 1,
            'value_int' => 50,
            'active' => true,
        ]);
        OrganizationAddon::query()->create([
            'organization_id' => $org->id,
            'addon_key' => 'api_rpm',
            'quantity' => 100,
            'value_int' => null,
            'active' => true,
        ]);

        $resolved = $this->entitlements->forOrg($org);

        $this->assertSame(5 + 50, $resolved['storage_gb']);
        $this->assertSame(60 + 100, $resolved['api_rpm']);
    }

    public function test_enabled_helper_returns_boolean(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_PRO,
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $this->assertTrue($this->entitlements->enabled('attendance', $org));
        $this->assertTrue($this->entitlements->enabled('api_access', $org));
        $this->assertSame(50, $this->entitlements->value('storage_gb', $org));
        $this->assertSame(300, $this->entitlements->value('api_rpm', $org));
    }

    public function test_tenant_scoping_addons_only_apply_to_own_org(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);
        OrganizationSubscription::query()->create([
            'organization_id' => $orgA->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 5,
        ]);
        OrganizationSubscription::query()->create([
            'organization_id' => $orgB->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 5,
        ]);
        OrganizationAddon::query()->create([
            'organization_id' => $orgA->id,
            'addon_key' => 'storage_gb',
            'value_int' => 100,
            'active' => true,
        ]);

        $resolvedA = $this->entitlements->forOrg($orgA);
        $resolvedB = $this->entitlements->forOrg($orgB);

        $this->assertSame(5 + 100, $resolvedA['storage_gb']);
        $this->assertSame(5, $resolvedB['storage_gb']);
    }
}
