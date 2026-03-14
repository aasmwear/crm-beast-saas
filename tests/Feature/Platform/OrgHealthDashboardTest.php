<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\OrganizationSubscription;
use App\Models\Platform\PlatformAdmin;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrgHealthDashboardTest extends TestCase
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

    public function test_platform_admin_can_access_org_health_dashboard(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->has('organizations')
            ->has('organizations.data')
            ->has('filters')
            ->has('statusOptions')
            ->has('healthOptions')
        );
    }

    public function test_tenant_user_cannot_access_org_health_dashboard(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);

        $response = $this->actingAs($user)
            ->get(route('platform.organizations.health'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_guest_cannot_access_org_health_dashboard(): void
    {
        $response = $this->get(route('platform.organizations.health'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_health_fields_present_for_org_with_subscription(): void
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
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->has('organizations.data.0')
            ->where('organizations.data.0.name', 'Acme Corp')
            ->where('organizations.data.0.slug', 'acme')
            ->where('organizations.data.0.has_stripe_id', true)
            ->where('organizations.data.0.plan_key', 'pro')
            ->where('organizations.data.0.status', 'active')
            ->where('organizations.data.0.seats_active', 2)
            ->where('organizations.data.0.seats_included', 25)
            ->has('organizations.data.0.storage')
            ->has('organizations.data.0.storage.used_gb')
            ->has('organizations.data.0.storage.limit_gb')
            ->has('organizations.data.0.storage.over_limit')
            ->has('organizations.data.0.webhook')
            ->has('organizations.data.0.health_flags')
            ->where('organizations.data.0.health_state', 'healthy')
            ->has('organizations.data.0.subscriptions_url')
            ->has('organizations.data.0.tenant_billing_url')
        );
    }

    public function test_fallback_when_org_has_no_subscription(): void
    {
        Organization::factory()->create([
            'name' => 'Legacy Org',
            'slug' => 'legacy',
            'plan' => 'starter',
            'stripe_id' => null,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->where('organizations.data.0.name', 'Legacy Org')
            ->where('organizations.data.0.plan_key', 'starter')
            ->where('organizations.data.0.status', 'none')
            ->where('organizations.data.0.has_stripe_id', false)
            ->where('organizations.data.0.health_state', 'healthy')
        );
    }

    public function test_critical_billing_status_sets_critical_health(): void
    {
        $org = Organization::factory()->create(['slug' => 'past-due-org']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'past_due',
            'seats_included' => 25,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->where('organizations.data.0.health_state', 'critical')
            ->where('organizations.data.0.health_flags.billing', 'critical')
        );
    }

    public function test_webhook_failures_set_health_flags(): void
    {
        $org = Organization::factory()->create([
            'name' => 'Webhook Org',
            'slug' => 'webhook-org',
            'stripe_id' => 'cus_webhook',
        ]);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_fail_1',
            'type' => 'invoice.payment_failed',
            'status' => 'failed',
            'notes' => 'Test failure',
            'organization_id' => $org->id,
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->where('organizations.data.0.webhook.recent_failed_count', 1)
            ->where('organizations.data.0.health_flags.webhooks', 'warning')
        );
    }

    public function test_many_webhook_failures_set_critical_flag(): void
    {
        $org = Organization::factory()->create([
            'name' => 'Bad Webhooks',
            'slug' => 'bad-webhooks',
            'stripe_id' => 'cus_bad',
        ]);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            StripeWebhookEvent::create([
                'stripe_event_id' => "evt_bad_$i",
                'type' => 'invoice.payment_failed',
                'status' => 'failed',
                'organization_id' => $org->id,
                'created_at' => now()->subDays($i),
            ]);
        }

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->where('organizations.data.0.webhook.recent_failed_count', 3)
            ->where('organizations.data.0.health_flags.webhooks', 'critical')
        );
    }

    public function test_seat_at_limit_sets_critical_flag(): void
    {
        $org = Organization::factory()->create(['slug' => 'full-seats']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 2,
            'seat_limit' => 2,
        ]);
        $org->users()->attach(User::factory()->create(['client_id' => null])->id, ['is_owner' => true]);
        $org->users()->attach(User::factory()->create(['client_id' => null])->id, []);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->where('organizations.data.0.seats_active', 2)
            ->where('organizations.data.0.health_flags.seats', 'critical')
        );
    }

    public function test_fallback_when_no_webhook_events(): void
    {
        Organization::factory()->create([
            'name' => 'No Webhooks',
            'slug' => 'no-webhooks',
            'stripe_id' => null,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->where('organizations.data.0.webhook.last_type', null)
            ->where('organizations.data.0.webhook.last_processed_at', null)
            ->where('organizations.data.0.webhook.last_status', null)
            ->where('organizations.data.0.webhook.recent_failed_count', 0)
        );
    }

    public function test_storage_fields_present(): void
    {
        $org = Organization::factory()->create(['slug' => 'storage-org']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->where('organizations.data.0.storage.used_gb', 0)
            ->where('organizations.data.0.storage.over_limit', false)
        );
    }

    public function test_search_filter(): void
    {
        Organization::factory()->create(['name' => 'Target Health', 'slug' => 'target-health']);
        Organization::factory()->create(['name' => 'Other Org', 'slug' => 'other-org']);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health', ['search' => 'Target']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->has('organizations.data', 1)
            ->where('organizations.data.0.name', 'Target Health')
        );
    }

    public function test_status_filter(): void
    {
        $activeOrg = Organization::factory()->create(['slug' => 'active-org']);
        OrganizationSubscription::create([
            'organization_id' => $activeOrg->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        $pastDueOrg = Organization::factory()->create(['slug' => 'pd-org']);
        OrganizationSubscription::create([
            'organization_id' => $pastDueOrg->id,
            'plan_key' => 'pro',
            'status' => 'past_due',
            'seats_included' => 25,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health', ['status' => 'past_due']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->has('organizations.data', 1)
            ->where('organizations.data.0.id', $pastDueOrg->id)
        );
    }

    public function test_health_filter_critical(): void
    {
        $healthyOrg = Organization::factory()->create(['slug' => 'healthy-org']);
        OrganizationSubscription::create([
            'organization_id' => $healthyOrg->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $criticalOrg = Organization::factory()->create(['slug' => 'critical-org']);
        OrganizationSubscription::create([
            'organization_id' => $criticalOrg->id,
            'plan_key' => 'pro',
            'status' => 'past_due',
            'seats_included' => 25,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health', ['health' => 'critical']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->has('organizations.data', 1)
            ->where('organizations.data.0.id', $criticalOrg->id)
        );
    }

    public function test_pagination(): void
    {
        Organization::factory()->count(25)->create();

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health', ['per_page' => 10]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Organizations/OrgHealth')
            ->where('organizations.per_page', 10)
            ->where('organizations.total', 25)
            ->where('organizations.last_page', 3)
        );
    }

    public function test_healthy_org_has_empty_health_flags(): void
    {
        $org = Organization::factory()->create(['slug' => 'good-org']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $data = $response->viewData('page')['props']['organizations']['data'][0];
        $this->assertEmpty($data['health_flags']);
        $this->assertEquals('healthy', $data['health_state']);
    }
}
