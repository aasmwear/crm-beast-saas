<?php

namespace Tests\Feature\Platform;

use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Platform\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RevenueDashboardTest extends TestCase
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

    public function test_platform_admin_can_access_revenue_dashboard(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Revenue/Index')
            ->has('total_orgs')
            ->has('stripe_linked_count')
            ->has('total_seats')
            ->has('subscription_counts')
            ->has('active_revenue_orgs')
            ->has('at_risk_orgs')
            ->has('plan_distribution')
            ->has('mrr')
            ->has('mrr.estimated_total_cents')
            ->has('mrr.by_plan')
        );
    }

    public function test_tenant_user_cannot_access_revenue_dashboard(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);

        $response = $this->actingAs($user)
            ->get(route('platform.revenue'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_guest_cannot_access_revenue_dashboard(): void
    {
        $response = $this->get(route('platform.revenue'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_total_orgs_count_is_accurate(): void
    {
        Organization::factory()->count(5)->create();

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('total_orgs', 5)
        );
    }

    public function test_subscription_status_counts(): void
    {
        $active = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $active->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $trialing = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $trialing->id,
            'plan_key' => 'pro',
            'status' => 'trialing',
            'seats_included' => 25,
        ]);

        $pastDue = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $pastDue->id,
            'plan_key' => 'starter',
            'status' => 'past_due',
            'seats_included' => 5,
        ]);

        Organization::factory()->create();

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('subscription_counts.active', 1)
            ->where('subscription_counts.trialing', 1)
            ->where('subscription_counts.past_due', 1)
            ->where('subscription_counts.none', 1)
            ->where('active_revenue_orgs', 2)
            ->where('at_risk_orgs', 1)
        );
    }

    public function test_plan_distribution_counts(): void
    {
        $org1 = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $org1->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $org2 = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $org2->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $org3 = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $org3->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        Organization::factory()->create(['plan' => 'starter']);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('plan_distribution.pro', 2)
            ->where('plan_distribution.starter', 2)
            ->where('plan_distribution.enterprise', 0)
        );
    }

    public function test_estimated_mrr_from_active_pro_orgs(): void
    {
        $org1 = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $org1->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $org2 = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $org2->id,
            'plan_key' => 'pro',
            'status' => 'trialing',
            'seats_included' => 25,
        ]);

        $org3 = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $org3->id,
            'plan_key' => 'pro',
            'status' => 'canceled',
            'seats_included' => 25,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $mrrData = $response->viewData('page')['props']['mrr'];
        $this->assertEquals(2 * 7900, $mrrData['estimated_total_cents']);
        $this->assertEquals(2, $mrrData['by_plan']['pro']['count']);
        $this->assertEquals(7900, $mrrData['by_plan']['pro']['price_cents']);
    }

    public function test_enterprise_orgs_excluded_from_mrr_with_note(): void
    {
        $org = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'enterprise',
            'status' => 'active',
            'seats_included' => 500,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $mrrData = $response->viewData('page')['props']['mrr'];
        $this->assertEquals(0, $mrrData['estimated_total_cents']);
        $this->assertEquals(1, $mrrData['by_plan']['enterprise']['count']);
        $this->assertNotNull($mrrData['enterprise_note']);
        $this->assertStringContainsString('enterprise', $mrrData['enterprise_note']);
    }

    public function test_stripe_linked_count(): void
    {
        Organization::factory()->create(['stripe_id' => 'cus_abc123']);
        Organization::factory()->create(['stripe_id' => 'cus_def456']);
        Organization::factory()->create(['stripe_id' => null]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stripe_linked_count', 2)
        );
    }

    public function test_total_seats_excludes_portal_users(): void
    {
        $org = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $staff1 = User::factory()->create(['client_id' => null]);
        $staff2 = User::factory()->create(['client_id' => null]);
        $portalUser = User::factory()->create(['client_id' => $client->id]);

        $org->users()->attach($staff1->id, ['is_owner' => true]);
        $org->users()->attach($staff2->id, []);
        $org->users()->attach($portalUser->id, []);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('total_seats', 2)
        );
    }

    public function test_fallback_when_no_orgs(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('total_orgs', 0)
            ->where('active_revenue_orgs', 0)
            ->where('at_risk_orgs', 0)
            ->where('mrr.estimated_total_cents', 0)
            ->where('total_seats', 0)
            ->where('stripe_linked_count', 0)
        );
    }

    public function test_fallback_when_no_subscriptions(): void
    {
        Organization::factory()->count(3)->create(['plan' => 'starter']);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('total_orgs', 3)
            ->where('subscription_counts.none', 3)
            ->where('subscription_counts.active', 0)
            ->where('plan_distribution.starter', 3)
            ->where('mrr.estimated_total_cents', 0)
        );
    }

    public function test_legacy_org_plan_falls_back_to_starter(): void
    {
        Organization::factory()->create(['plan' => '']);
        Organization::factory()->create(['plan' => 'trial']);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.revenue'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('plan_distribution.starter', 2)
        );
    }
}
