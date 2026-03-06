<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\OrganizationSubscription;
use App\Services\Billing\EntitlementsService;
use App\Support\PlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonsSemanticsTest extends TestCase
{
    use RefreshDatabase;

    private EntitlementsService $entitlements;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entitlements = app(EntitlementsService::class);
    }

    public function test_augment_addon_increases_numeric_entitlement(): void
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
            'value_int' => 10,
            'mode' => OrganizationAddon::MODE_AUGMENT,
            'active' => true,
        ]);

        $resolved = $this->entitlements->forOrg($org);

        $this->assertSame(5 + 10, $resolved['storage_gb']);
    }

    public function test_set_addon_overrides_numeric_entitlement(): void
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
            'addon_key' => 'api_rpm',
            'value_int' => 600,
            'mode' => OrganizationAddon::MODE_SET,
            'active' => true,
        ]);

        $resolved = $this->entitlements->forOrg($org);

        $this->assertSame(600, $resolved['api_rpm']);
        $this->assertSame(5, $resolved['storage_gb']); // unchanged, no addon
    }

    public function test_multiple_set_addons_choose_highest(): void
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
            'value_int' => 100,
            'mode' => OrganizationAddon::MODE_SET,
            'active' => true,
        ]);
        OrganizationAddon::query()->create([
            'organization_id' => $org->id,
            'addon_key' => 'storage_gb',
            'value_int' => 200,
            'mode' => OrganizationAddon::MODE_SET,
            'active' => true,
        ]);

        $resolved = $this->entitlements->forOrg($org);

        $this->assertSame(200, $resolved['storage_gb']);
    }

    public function test_subscription_plan_key_used_over_organizations_plan_when_both_exist(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme', 'plan' => 'enterprise']);
        OrganizationSubscription::query()->create([
            'organization_id' => $org->id,
            'plan_key' => PlanCatalog::PLAN_STARTER,
            'status' => 'active',
            'seats_included' => 5,
        ]);

        $resolved = $this->entitlements->forOrg($org);

        $this->assertSame(5, $resolved['storage_gb']);
        $this->assertSame(60, $resolved['api_rpm']);
    }

    public function test_fallback_organizations_plan_when_subscription_missing(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme', 'plan' => PlanCatalog::PLAN_PRO]);

        $resolved = $this->entitlements->forOrg($org);

        $this->assertSame(50, $resolved['storage_gb']);
        $this->assertSame(300, $resolved['api_rpm']);
    }

    public function test_existing_behavior_unaffected_when_mode_not_provided_defaults_augment(): void
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
            'value_int' => 25,
            'active' => true,
        ]);

        $resolved = $this->entitlements->forOrg($org);

        $this->assertSame(5 + 25, $resolved['storage_gb']);
    }
}
