<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Platform\PlatformAdmin;
use App\Models\StripeWebhookEvent;
use App\Services\Webhooks\WebhookSummaryService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemPerformanceDashboardTest extends TestCase
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

    public function test_platform_admin_can_access_system_performance_dashboard(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/SystemPerformance/Index')
            ->has('readiness')
            ->has('readiness.status')
            ->has('readiness.checks')
            ->has('readiness.timestamp')
            ->has('queue_health')
            ->has('webhook_health')
            ->has('storage_pressure')
            ->has('at_risk_orgs')
            ->has('platform_summary')
        );
    }

    public function test_tenant_user_cannot_access_system_performance_dashboard(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);

        $response = $this->actingAs($user)
            ->get(route('platform.system-performance'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_guest_cannot_access_system_performance_dashboard(): void
    {
        $response = $this->get(route('platform.system-performance'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_readiness_shows_healthy_when_all_checks_pass(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/SystemPerformance/Index')
            ->where('readiness.status', 'healthy')
            ->where('readiness.checks.database.ok', true)
            ->where('readiness.checks.cache.ok', true)
            ->where('readiness.checks.queue.ok', true)
        );
    }

    public function test_queue_health_counts_failed_jobs(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-1',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test exception',
            'failed_at' => now()->subHours(2),
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-2',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test exception 2',
            'failed_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('queue_health.total_failed', 2)
            ->where('queue_health.failed_last_24h', 1)
            ->where('queue_health.failed_last_7d', 2)
            ->has('queue_health.recent_failures', 2)
        );
    }

    public function test_webhook_health_counts_failures(): void
    {
        $org = Organization::factory()->create(['slug' => 'webhook-org', 'stripe_id' => 'cus_test']);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_ok_1',
            'type' => 'customer.subscription.updated',
            'status' => 'processed',
            'organization_id' => $org->id,
            'processed_at' => now()->subHour(),
        ]);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_fail_1',
            'type' => 'invoice.payment_failed',
            'status' => 'failed',
            'notes' => 'Test failure',
            'organization_id' => $org->id,
            'created_at' => now()->subHours(2),
        ]);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_fail_2',
            'type' => 'invoice.payment_failed',
            'status' => 'failed',
            'notes' => 'Test failure 2',
            'organization_id' => $org->id,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('webhook_health.total_events', 3)
            ->where('webhook_health.processed_count', 1)
            ->where('webhook_health.failed_count', 2)
            ->where('webhook_health.failed_last_7d', 2)
            ->where('webhook_health.orgs_with_failures_7d', 1)
            ->has('webhook_health.recent_failures', 2)
        );
    }

    public function test_webhook_health_matches_summaries_when_read_model_is_populated(): void
    {
        $org = Organization::factory()->create(['slug' => 'webhook-org-sum', 'stripe_id' => 'cus_test_sum']);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_sum_ok',
            'type' => 'customer.subscription.updated',
            'status' => 'processed',
            'organization_id' => $org->id,
            'processed_at' => now()->subHour(),
        ]);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_sum_fail',
            'type' => 'invoice.payment_failed',
            'status' => 'failed',
            'notes' => 'Test failure',
            'organization_id' => $org->id,
            'created_at' => now()->subHours(2),
        ]);

        app(WebhookSummaryService::class)->rebuildAll();

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('webhook_health.total_events', 2)
            ->where('webhook_health.processed_count', 1)
            ->where('webhook_health.failed_count', 1)
            ->where('webhook_health.failed_last_7d', 1)
            ->where('webhook_health.orgs_with_failures_7d', 1)
            ->has('webhook_health.recent_failures', 1)
        );
    }

    public function test_at_risk_orgs_counts_billing_issues(): void
    {
        $org1 = Organization::factory()->create(['slug' => 'past-due-org']);
        OrganizationSubscription::create([
            'organization_id' => $org1->id,
            'plan_key' => 'pro',
            'status' => 'past_due',
            'seats_included' => 25,
        ]);

        $org2 = Organization::factory()->create(['slug' => 'unpaid-org']);
        OrganizationSubscription::create([
            'organization_id' => $org2->id,
            'plan_key' => 'starter',
            'status' => 'unpaid',
            'seats_included' => 5,
        ]);

        $org3 = Organization::factory()->create(['slug' => 'healthy-org']);
        OrganizationSubscription::create([
            'organization_id' => $org3->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('at_risk_orgs.past_due_count', 1)
            ->where('at_risk_orgs.unpaid_count', 1)
            ->where('at_risk_orgs.billing_at_risk', 2)
        );
    }

    public function test_at_risk_orgs_counts_webhook_failures(): void
    {
        $org = Organization::factory()->create(['slug' => 'bad-wh-org', 'stripe_id' => 'cus_bad']);

        for ($i = 1; $i <= 4; $i++) {
            StripeWebhookEvent::create([
                'stripe_event_id' => "evt_risk_$i",
                'type' => 'invoice.payment_failed',
                'status' => 'failed',
                'organization_id' => $org->id,
                'created_at' => now()->subDays($i),
            ]);
        }

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('at_risk_orgs.webhook_at_risk', 1)
            ->where('at_risk_orgs.orgs_3plus_webhook_failures', 1)
        );
    }

    public function test_storage_pressure_shows_zero_state(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('storage_pressure.orgs_over_limit', 0)
            ->where('storage_pressure.orgs_near_limit', 0)
            ->where('storage_pressure.total_storage_used_gb', 0)
            ->has('storage_pressure.details', 0)
        );
    }

    public function test_platform_summary_includes_counts(): void
    {
        $org = Organization::factory()->create(['slug' => 'summary-org', 'stripe_id' => 'cus_sum']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['is_owner' => true]);

        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $data = $response->viewData('page')['props']['platform_summary'];
        $this->assertGreaterThanOrEqual(1, $data['total_orgs']);
        $this->assertGreaterThanOrEqual(1, $data['total_users']);
        $this->assertGreaterThanOrEqual(1, $data['active_subscriptions']);
        $this->assertGreaterThanOrEqual(1, $data['stripe_linked']);
    }

    public function test_zero_state_no_orgs(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('webhook_health.total_events', 0)
            ->where('webhook_health.failed_count', 0)
            ->where('at_risk_orgs.billing_at_risk', 0)
            ->where('at_risk_orgs.webhook_at_risk', 0)
            ->where('storage_pressure.orgs_over_limit', 0)
            ->where('platform_summary.total_orgs', 0)
        );
    }

    public function test_queue_health_zero_state(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('queue_health.total_failed', 0)
            ->where('queue_health.failed_last_24h', 0)
            ->where('queue_health.failed_last_7d', 0)
            ->has('queue_health.recent_failures', 0)
        );
    }

    public function test_webhook_failed_last_24h_only_counts_recent(): void
    {
        $org = Organization::factory()->create(['slug' => 'wh-24h', 'stripe_id' => 'cus_24h']);

        $recent = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_recent',
            'type' => 'invoice.payment_failed',
            'status' => 'failed',
            'organization_id' => $org->id,
        ]);
        DB::table('stripe_webhook_events')
            ->where('id', $recent->id)
            ->update(['created_at' => now()->subHours(12)]);

        $old = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_old',
            'type' => 'invoice.payment_failed',
            'status' => 'failed',
            'organization_id' => $org->id,
        ]);
        DB::table('stripe_webhook_events')
            ->where('id', $old->id)
            ->update(['created_at' => now()->subDays(3)]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('webhook_health.failed_last_24h', 1)
            ->where('webhook_health.failed_last_7d', 2)
        );
    }

    public function test_readiness_includes_driver_info(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $data = $response->viewData('page')['props']['readiness'];
        $this->assertNotEmpty($data['checks']['database']['driver']);
        $this->assertNotEmpty($data['checks']['cache']['driver']);
        $this->assertNotEmpty($data['checks']['queue']['driver']);
    }
}
