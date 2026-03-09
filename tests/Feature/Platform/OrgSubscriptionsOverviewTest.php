<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\OrganizationSubscription;
use App\Models\Platform\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrgSubscriptionsOverviewTest extends TestCase
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

    public function test_platform_admin_can_access_subscriptions_page(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.subscriptions'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/SubscriptionsIndex')
            ->has('organizations')
            ->has('organizations.data')
            ->has('filters')
            ->has('planKeys')
            ->has('statusOptions')
        );
    }

    public function test_tenant_user_cannot_access_subscriptions_page(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);

        $response = $this->actingAs($user)
            ->get(route('platform.organizations.subscriptions'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_guest_cannot_access_subscriptions_page(): void
    {
        $response = $this->get(route('platform.organizations.subscriptions'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_page_includes_canonical_billing_data_for_org_with_subscription(): void
    {
        $org = Organization::factory()->create([
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'stripe_id' => 'cus_test123',
        ]);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
            'seat_limit' => null,
        ]);
        $org->users()->attach(User::factory()->create(['client_id' => null])->id, ['is_owner' => true]);
        $org->users()->attach(User::factory()->create(['client_id' => null])->id, []);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.subscriptions'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/SubscriptionsIndex')
            ->has('organizations.data.0')
            ->where('organizations.data.0.id', $org->id)
            ->where('organizations.data.0.name', 'Acme Corp')
            ->where('organizations.data.0.slug', 'acme')
            ->where('organizations.data.0.has_stripe_id', true)
            ->where('organizations.data.0.plan_key', 'pro')
            ->where('organizations.data.0.status', 'active')
            ->where('organizations.data.0.seats_included', 25)
            ->where('organizations.data.0.active_seats', 2)
            ->has('organizations.data.0.entitlements')
            ->has('organizations.data.0.entitlements.api_rpm')
            ->has('organizations.data.0.entitlements.storage_gb')
            ->has('organizations.data.0.entitlements.exports_per_day')
        );
    }

    public function test_fallback_behavior_when_org_has_no_subscription_row(): void
    {
        $org = Organization::factory()->create([
            'name' => 'Legacy Org',
            'slug' => 'legacy',
            'plan' => 'starter',
            'stripe_id' => null,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.subscriptions'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/SubscriptionsIndex')
            ->where('organizations.data.0.name', 'Legacy Org')
            ->where('organizations.data.0.plan_key', 'starter')
            ->where('organizations.data.0.status', 'none')
            ->where('organizations.data.0.has_stripe_id', false)
        );
    }

    public function test_pagination_works(): void
    {
        Organization::factory()->count(20)->create();

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.subscriptions', ['per_page' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/SubscriptionsIndex')
            ->where('organizations.per_page', 10)
            ->where('organizations.last_page', 2)
            ->where('organizations.total', 20)
            ->has('organizations.data.9')
        );
    }

    public function test_search_filter_by_name(): void
    {
        Organization::factory()->create(['name' => 'Target Org', 'slug' => 'target']);
        Organization::factory()->create(['name' => 'Other Org', 'slug' => 'other']);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.subscriptions', ['search' => 'Target']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/SubscriptionsIndex')
            ->has('organizations.data', 1)
            ->where('organizations.data.0.name', 'Target Org')
        );
    }

    public function test_status_filter(): void
    {
        $activeOrg = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $activeOrg->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        $pastDueOrg = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $pastDueOrg->id,
            'plan_key' => 'pro',
            'status' => 'past_due',
            'seats_included' => 25,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.subscriptions', ['status' => 'past_due']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/SubscriptionsIndex')
            ->has('organizations.data', 1)
            ->where('organizations.data.0.id', $pastDueOrg->id)
        );
    }

    public function test_addons_and_entitlements_included(): void
    {
        $org = Organization::factory()->create(['name' => 'Addon Org', 'slug' => 'addon-org']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);
        OrganizationAddon::create([
            'organization_id' => $org->id,
            'addon_key' => 'storage_gb',
            'mode' => 'augment',
            'quantity' => 1,
            'value_int' => 10,
            'active' => true,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.subscriptions'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/SubscriptionsIndex')
            ->where('organizations.data.0.name', 'Addon Org')
            ->has('organizations.data.0.addons_summary.0')
            ->where('organizations.data.0.addons_summary.0.addon_key', 'storage_gb')
            ->where('organizations.data.0.addons_summary.0.value_int', 10)
        );
        $entitlements = $response->viewData('page')['props']['organizations']['data'][0]['entitlements'] ?? [];
        $this->assertGreaterThanOrEqual(10, $entitlements['storage_gb'] ?? 0);
    }
}
