<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrgDailyMetric;
use App\Models\Platform\PlatformAdmin;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\OrgMetricsSnapshotService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Config::set('org_daily_metrics.snapshot_only_all_tenants', false);
        parent::tearDown();
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

    public function test_seats_active_uses_snapshot_users_count_when_row_exists(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-03-22 12:00:00'));
        $yesterday = CarbonImmutable::yesterday();

        $org = Organization::factory()->create(['slug' => 'snapshot-seats']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);
        $org->users()->attach(User::factory()->create(['client_id' => null])->id, ['is_owner' => true]);
        $org->users()->attach(User::factory()->create(['client_id' => null])->id, []);

        app(OrgMetricsSnapshotService::class)->snapshotOrg($org, $yesterday);

        $org->users()->attach(User::factory()->create(['client_id' => null])->id, []);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organizations.data.0.seats_active', 2)
        );
    }

    public function test_seats_active_falls_back_to_live_without_snapshot_row(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-03-22 12:00:00'));

        $org = Organization::factory()->create(['slug' => 'live-seats-only']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);
        $org->users()->attach(User::factory()->create(['client_id' => null])->id, []);
        $org->users()->attach(User::factory()->create(['client_id' => null])->id, []);

        $this->assertSame(0, OrgDailyMetric::query()->count());

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organizations.data.0.seats_active', 2)
        );
    }

    public function test_seats_hybrid_per_org_isolation_on_same_page(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-03-25 10:00:00'));
        $yesterday = CarbonImmutable::yesterday();
        $snapshot = app(OrgMetricsSnapshotService::class);

        $orgSnap = Organization::factory()->create(['name' => 'Apple Health Co', 'slug' => 'apple-health']);
        OrganizationSubscription::create([
            'organization_id' => $orgSnap->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);
        $orgSnap->users()->attach(User::factory()->create(['client_id' => null])->id, []);
        $snapshot->snapshotOrg($orgSnap, $yesterday);
        for ($i = 0; $i < 4; $i++) {
            $orgSnap->users()->attach(User::factory()->create(['client_id' => null])->id, []);
        }

        $orgLive = Organization::factory()->create(['name' => 'Zebra Health Co', 'slug' => 'zebra-health']);
        OrganizationSubscription::create([
            'organization_id' => $orgLive->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);
        for ($i = 0; $i < 3; $i++) {
            $orgLive->users()->attach(User::factory()->create(['client_id' => null])->id, []);
        }

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $data = $response->viewData('page')['props']['organizations']['data'];
        $this->assertCount(2, $data);
        $this->assertSame('Apple Health Co', $data[0]['name']);
        $this->assertSame(1, $data[0]['seats_active']);
        $this->assertSame('Zebra Health Co', $data[1]['name']);
        $this->assertSame(3, $data[1]['seats_active']);
    }

    public function test_billing_and_webhooks_still_live_when_snapshot_seats_used(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-03-22 12:00:00'));
        $yesterday = CarbonImmutable::yesterday();

        $org = Organization::factory()->create([
            'name' => 'Billing Webhook Org',
            'slug' => 'bw-org',
            'stripe_id' => 'cus_bw',
        ]);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'past_due',
            'seats_included' => 25,
        ]);
        $org->users()->attach(User::factory()->create(['client_id' => null])->id, []);
        app(OrgMetricsSnapshotService::class)->snapshotOrg($org, $yesterday);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_bw_1',
            'type' => 'invoice.payment_failed',
            'status' => 'failed',
            'notes' => 'Test',
            'organization_id' => $org->id,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organizations.data.0.status', 'past_due')
            ->where('organizations.data.0.has_stripe_id', true)
            ->where('organizations.data.0.health_flags.billing', 'critical')
            ->where('organizations.data.0.webhook.recent_failed_count', 1)
            ->where('organizations.data.0.health_flags.webhooks', 'warning')
        );
    }

    public function test_large_tenant_seats_use_latest_snapshot_when_yesterday_row_missing(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-04-10 12:00:00'));

        $org = Organization::factory()->create([
            'slug' => 'large-snapshot-latest',
            'tier' => 'large',
        ]);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 100,
        ]);
        for ($i = 0; $i < 7; $i++) {
            $org->users()->attach(User::factory()->create(['client_id' => null])->id, []);
        }

        OrgDailyMetric::query()->create([
            'organization_id' => $org->id,
            'metric_date' => CarbonImmutable::parse('2026-04-05')->toDateString(),
            'clients_count' => 0,
            'projects_count' => 0,
            'tasks_count' => 0,
            'open_tasks_count' => 0,
            'attendance_count' => 0,
            'activities_count' => 0,
            'invoices_count' => 0,
            'revenue_cents' => 0,
            'outstanding_cents' => 0,
            'users_count' => 3,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organizations.data.0.seats_active', 3)
        );
    }

    public function test_small_tenant_seats_ignore_stale_snapshot_without_yesterday_row(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-04-10 12:00:00'));

        $org = Organization::factory()->create([
            'slug' => 'small-no-yesterday-snap',
            'tier' => 'small',
        ]);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 25,
        ]);
        for ($i = 0; $i < 4; $i++) {
            $org->users()->attach(User::factory()->create(['client_id' => null])->id, []);
        }

        OrgDailyMetric::query()->create([
            'organization_id' => $org->id,
            'metric_date' => CarbonImmutable::parse('2026-04-05')->toDateString(),
            'clients_count' => 0,
            'projects_count' => 0,
            'tasks_count' => 0,
            'open_tasks_count' => 0,
            'attendance_count' => 0,
            'activities_count' => 0,
            'invoices_count' => 0,
            'revenue_cents' => 0,
            'outstanding_cents' => 0,
            'users_count' => 1,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organizations.data.0.seats_active', 4)
        );
    }

    public function test_config_all_tenants_snapshot_read_uses_latest_row_for_small_tier(): void
    {
        Config::set('org_daily_metrics.snapshot_only_all_tenants', true);

        Carbon::setTestNow(CarbonImmutable::parse('2026-04-10 12:00:00'));

        $org = Organization::factory()->create([
            'slug' => 'all-tenants-snapshot-read',
            'tier' => 'small',
        ]);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 25,
        ]);
        for ($i = 0; $i < 6; $i++) {
            $org->users()->attach(User::factory()->create(['client_id' => null])->id, []);
        }

        OrgDailyMetric::query()->create([
            'organization_id' => $org->id,
            'metric_date' => CarbonImmutable::parse('2026-04-06')->toDateString(),
            'clients_count' => 0,
            'projects_count' => 0,
            'tasks_count' => 0,
            'open_tasks_count' => 0,
            'attendance_count' => 0,
            'activities_count' => 0,
            'invoices_count' => 0,
            'revenue_cents' => 0,
            'outstanding_cents' => 0,
            'users_count' => 2,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.organizations.health'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('organizations.data.0.seats_active', 2)
        );
    }
}
