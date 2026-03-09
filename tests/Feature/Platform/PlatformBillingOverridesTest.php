<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\OrganizationSubscription;
use App\Models\Platform\PlatformAdmin;
use App\Models\User;
use App\Services\Billing\EntitlementsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformBillingOverridesTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformAdmin $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platformAdmin = PlatformAdmin::factory()->superAdmin()->create([
            'email' => 'admin@platform.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    public function test_platform_admin_can_override_plan_key(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->patch(route('platform.organizations.subscription.update', ['organization' => $org->slug]), [
                'plan_key' => 'pro',
            ]);

        $response->assertRedirect(route('platform.organizations.subscriptions'));
        $response->assertSessionHas('success');

        $org->refresh();
        $sub = $org->billingSubscription;
        $this->assertNotNull($sub);
        $this->assertSame('pro', $sub->plan_key);
    }

    public function test_platform_admin_can_set_seat_limit(): void
    {
        $org = Organization::factory()->create(['slug' => 'beta']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
            'seat_limit' => null,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->patch(route('platform.organizations.subscription.update', ['organization' => $org->slug]), [
                'plan_key' => 'pro',
                'seat_limit' => 30,
            ]);

        $response->assertRedirect();
        $org->refresh();
        $this->assertSame(30, $org->billingSubscription?->seat_limit);
    }

    public function test_platform_admin_can_clear_seat_limit(): void
    {
        $org = Organization::factory()->create(['slug' => 'gamma']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
            'seat_limit' => 30,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->patch(route('platform.organizations.subscription.update', ['organization' => $org->slug]), [
                'plan_key' => 'pro',
                'clear_seat_limit' => true,
            ]);

        $response->assertRedirect();
        $org->refresh();
        $this->assertNull($org->billingSubscription?->seat_limit);
    }

    public function test_platform_admin_can_create_addon(): void
    {
        $org = Organization::factory()->create(['slug' => 'delta']);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->post(route('platform.organizations.addons.store', ['organization' => $org->slug]), [
                'addon_key' => 'storage_gb',
                'mode' => 'augment',
                'value_int' => 10,
                'active' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organization_addons', [
            'organization_id' => $org->id,
            'addon_key' => 'storage_gb',
            'mode' => 'augment',
            'value_int' => 10,
            'active' => true,
        ]);
    }

    public function test_platform_admin_can_update_addon(): void
    {
        $org = Organization::factory()->create(['slug' => 'epsilon']);
        $addon = OrganizationAddon::create([
            'organization_id' => $org->id,
            'addon_key' => 'api_rpm',
            'mode' => 'augment',
            'quantity' => 1,
            'value_int' => 100,
            'active' => true,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->patch(route('platform.organizations.addons.update', ['organization' => $org->slug, 'addon' => $addon->id]), [
                'mode' => 'set',
                'value_int' => 500,
                'active' => true,
            ]);

        $response->assertRedirect();
        $addon->refresh();
        $this->assertSame('set', $addon->mode);
        $this->assertSame(500, $addon->value_int);
    }

    public function test_platform_admin_can_deactivate_addon(): void
    {
        $org = Organization::factory()->create(['slug' => 'zeta']);
        $addon = OrganizationAddon::create([
            'organization_id' => $org->id,
            'addon_key' => 'storage_gb',
            'mode' => 'augment',
            'quantity' => 1,
            'value_int' => 10,
            'active' => true,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->delete(route('platform.organizations.addons.destroy', ['organization' => $org->slug, 'addon' => $addon->id]));

        $response->assertRedirect();
        $addon->refresh();
        $this->assertFalse($addon->active);
    }

    public function test_tenant_user_cannot_override_subscription(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['is_owner' => true]);

        $response = $this->actingAs($user)
            ->patch(route('platform.organizations.subscription.update', ['organization' => $org->slug]), [
                'plan_key' => 'pro',
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_tenant_user_cannot_create_addon(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);

        $response = $this->actingAs($user)
            ->post(route('platform.organizations.addons.store', ['organization' => $org->slug]), [
                'addon_key' => 'storage_gb',
                'mode' => 'augment',
                'value_int' => 10,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('organization_addons', 0);
    }

    public function test_organization_subscriptions_upserted_when_missing(): void
    {
        $org = Organization::factory()->create(['slug' => 'neworg']);

        $this->actingAs($this->platformAdmin, 'platform')
            ->patch(route('platform.organizations.subscription.update', ['organization' => $org->slug]), [
                'plan_key' => 'enterprise',
            ]);

        $sub = OrganizationSubscription::query()->where('organization_id', $org->id)->first();
        $this->assertNotNull($sub);
        $this->assertSame('enterprise', $sub->plan_key);
    }

    public function test_entitlements_reflect_addon_after_mutation(): void
    {
        $org = Organization::factory()->create(['slug' => 'ent']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        $this->actingAs($this->platformAdmin, 'platform')
            ->post(route('platform.organizations.addons.store', ['organization' => $org->slug]), [
                'addon_key' => 'storage_gb',
                'mode' => 'augment',
                'value_int' => 10,
                'active' => true,
            ]);

        app(EntitlementsService::class)->clearCache($org);
        $entitlements = app(EntitlementsService::class)->forOrg($org->fresh());
        $this->assertGreaterThanOrEqual(15, $entitlements['storage_gb'] ?? 0);
    }

    public function test_org_scoping_enforced_cannot_mutate_other_org_addon(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);
        $addonA = OrganizationAddon::create([
            'organization_id' => $orgA->id,
            'addon_key' => 'storage_gb',
            'mode' => 'augment',
            'quantity' => 1,
            'value_int' => 10,
            'active' => true,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->patch(route('platform.organizations.addons.update', ['organization' => $orgB->slug, 'addon' => $addonA->id]), [
                'value_int' => 999,
            ]);

        $response->assertStatus(404);
        $addonA->refresh();
        $this->assertSame(10, $addonA->value_int);
    }

    public function test_audit_log_created_on_subscription_override(): void
    {
        $org = Organization::factory()->create(['slug' => 'audit-org']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        $this->actingAs($this->platformAdmin, 'platform')
            ->patch(route('platform.organizations.subscription.update', ['organization' => $org->slug]), [
                'plan_key' => 'pro',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'platform_subscription_override',
            'entity' => 'subscription',
        ]);
    }

    public function test_audit_log_created_on_addon_created(): void
    {
        $org = Organization::factory()->create(['slug' => 'audit-addon']);

        $this->actingAs($this->platformAdmin, 'platform')
            ->post(route('platform.organizations.addons.store', ['organization' => $org->slug]), [
                'addon_key' => 'api_rpm',
                'mode' => 'set',
                'value_int' => 600,
                'active' => true,
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'platform_addon_created',
            'entity' => 'addon',
        ]);
    }
}
