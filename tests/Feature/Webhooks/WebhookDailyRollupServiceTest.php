<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Models\Organization;
use App\Models\StripeWebhookEvent;
use App\Models\WebhookEventDailyRollup;
use App\Services\Webhooks\WebhookDailyRollupService;
use App\Services\Webhooks\WebhookSummaryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class WebhookDailyRollupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_all_groups_by_calendar_day_and_status(): void
    {
        $org = Organization::factory()->create();

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_p1',
            'type' => 'customer.subscription.updated',
            'status' => 'processed',
            'processed_at' => now(),
            'organization_id' => $org->id,
        ]);
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_f1',
            'type' => 'customer.subscription.updated',
            'status' => 'failed',
            'notes' => 'x',
            'organization_id' => $org->id,
        ]);

        $n = app(WebhookDailyRollupService::class)->rebuildAll();
        $this->assertSame(1, $n);

        $row = WebhookEventDailyRollup::query()
            ->where('provider', 'stripe')
            ->where('organization_scope', (string) $org->id)
            ->where('event_type', 'customer.subscription.updated')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(2, $row->total_count);
        $this->assertSame(1, $row->success_count);
        $this->assertSame(1, $row->failure_count);
    }

    public function test_rebuild_is_idempotent(): void
    {
        $org = Organization::factory()->create();
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_idem',
            'type' => 'ping',
            'status' => 'processed',
            'processed_at' => now(),
            'organization_id' => $org->id,
        ]);

        $svc = app(WebhookDailyRollupService::class);
        $svc->rebuildAll();
        $c = WebhookEventDailyRollup::count();
        $svc->rebuildAll();
        $this->assertSame($c, WebhookEventDailyRollup::count());
    }

    public function test_scoped_rebuild_leaves_other_orgs_rollups_intact(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_a',
            'type' => 't',
            'status' => 'processed',
            'processed_at' => now(),
            'organization_id' => $org1->id,
        ]);
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_b',
            'type' => 't',
            'status' => 'processed',
            'processed_at' => now(),
            'organization_id' => $org2->id,
        ]);

        $svc = app(WebhookDailyRollupService::class);
        $svc->rebuildAll();
        $this->assertSame(2, WebhookEventDailyRollup::count());

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_a2',
            'type' => 't',
            'status' => 'failed',
            'organization_id' => $org1->id,
        ]);

        $svc->rebuildForOrganizationScope($org1->id);
        $this->assertSame(2, WebhookEventDailyRollup::count());

        $r1 = WebhookEventDailyRollup::query()
            ->where('organization_scope', (string) $org1->id)
            ->where('event_type', 't')
            ->first();
        $this->assertSame(2, $r1->total_count);
        $this->assertSame(1, $r1->failure_count);

        $r2 = WebhookEventDailyRollup::query()
            ->where('organization_scope', (string) $org2->id)
            ->where('event_type', 't')
            ->first();
        $this->assertSame(1, $r2->total_count);
    }

    public function test_days_window_rebuild_only_touches_recent_rollups(): void
    {
        $org = Organization::factory()->create();

        $old = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_old_d',
            'type' => 'old_t',
            'status' => 'processed',
            'processed_at' => now()->subDays(40),
            'organization_id' => $org->id,
        ]);
        \Illuminate\Support\Facades\DB::table('stripe_webhook_events')->where('id', $old->id)->update([
            'created_at' => now()->subDays(40),
        ]);

        $fresh = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_new_d',
            'type' => 'new_t',
            'status' => 'processed',
            'processed_at' => now(),
            'organization_id' => $org->id,
        ]);

        app(WebhookDailyRollupService::class)->rebuildAll();
        $this->assertGreaterThanOrEqual(2, WebhookEventDailyRollup::count());

        \Illuminate\Support\Facades\DB::table('stripe_webhook_events')->where('id', $fresh->id)->delete();

        app(WebhookDailyRollupService::class)->rebuildAll(WebhookDailyRollupService::DEFAULT_PROVIDER, 10);

        $this->assertDatabaseMissing('webhook_event_daily_rollups', [
            'event_type' => 'new_t',
            'organization_scope' => (string) $org->id,
        ]);
        $this->assertDatabaseHas('webhook_event_daily_rollups', [
            'event_type' => 'old_t',
            'organization_scope' => (string) $org->id,
        ]);
    }

    public function test_artisan_rebuild_daily_rollups_command(): void
    {
        Organization::factory()->create();
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_cmd_d',
            'type' => 'cmd_t',
            'status' => 'failed',
            'organization_id' => null,
        ]);

        Artisan::call('webhooks:rebuild-daily-rollups');
        $this->assertDatabaseHas('webhook_event_daily_rollups', [
            'provider' => 'stripe',
            'organization_scope' => 'unscoped',
            'event_type' => 'cmd_t',
            'failure_count' => 1,
        ]);
    }

    public function test_webhooks_rebuild_daily_rollups_is_scheduled_utc(): void
    {
        $commands = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($event) => ['command' => $event->command, 'timezone' => $event->timezone])
            ->filter(fn ($row) => $row['command'] !== null)
            ->values();

        $match = $commands->first(fn ($row) => str_contains((string) $row['command'], 'webhooks:rebuild-daily-rollups'));
        $this->assertNotNull($match);
        $this->assertSame('UTC', $match['timezone']);
    }

    public function test_system_performance_uses_rollups_for_window_counts_when_present(): void
    {
        $admin = \App\Models\Platform\PlatformAdmin::factory()->superAdmin()->create([
            'email' => 'rollup-admin@test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $org = Organization::factory()->create(['slug' => 'rollup-org', 'stripe_id' => 'cus_rl']);

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_r_ok',
            'type' => 'customer.subscription.updated',
            'status' => 'processed',
            'organization_id' => $org->id,
            'processed_at' => now()->subHour(),
        ]);
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_r_fail',
            'type' => 'invoice.payment_failed',
            'status' => 'failed',
            'notes' => 'n',
            'organization_id' => $org->id,
            'created_at' => now()->subHours(2),
        ]);

        app(WebhookSummaryService::class)->rebuildAll();
        app(WebhookDailyRollupService::class)->rebuildAll();

        $response = $this->actingAs($admin, 'platform')
            ->get(route('platform.system-performance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('webhook_health.total_events', 2)
            ->where('webhook_health.processed_count', 1)
            ->where('webhook_health.failed_count', 1)
            ->where('webhook_health.failed_last_24h', 1)
            ->where('webhook_health.failed_last_7d', 1)
            ->where('webhook_health.orgs_with_failures_7d', 1)
            ->has('webhook_health.recent_failures', 1)
        );
    }
}
