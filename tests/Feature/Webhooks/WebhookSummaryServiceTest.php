<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Models\Organization;
use App\Models\StripeWebhookEvent;
use App\Models\WebhookEventSummary;
use App\Services\Webhooks\WebhookSummaryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class WebhookSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_all_aggregates_success_and_failure_counts(): void
    {
        $org = Organization::factory()->create();

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_ok',
            'type' => 'customer.subscription.updated',
            'status' => 'processed',
            'processed_at' => now(),
            'organization_id' => $org->id,
        ]);
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_fail',
            'type' => 'customer.subscription.updated',
            'status' => 'failed',
            'notes' => 'boom',
            'organization_id' => $org->id,
        ]);
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_pending',
            'type' => 'invoice.payment_succeeded',
            'status' => 'received',
            'organization_id' => null,
        ]);

        $service = app(WebhookSummaryService::class);
        $n = $service->rebuildAll();
        $this->assertGreaterThanOrEqual(2, $n);

        $orgRow = WebhookEventSummary::query()
            ->where('provider', 'stripe')
            ->where('organization_scope', (string) $org->id)
            ->where('event_type', 'customer.subscription.updated')
            ->first();
        $this->assertNotNull($orgRow);
        $this->assertSame(2, $orgRow->total_count);
        $this->assertSame(1, $orgRow->success_count);
        $this->assertSame(1, $orgRow->failure_count);
        $this->assertSame('boom', $orgRow->last_error_message);

        $unscoped = WebhookEventSummary::query()
            ->where('organization_scope', 'unscoped')
            ->where('event_type', 'invoice.payment_succeeded')
            ->first();
        $this->assertNotNull($unscoped);
        $this->assertSame(1, $unscoped->total_count);
        $this->assertSame(0, $unscoped->success_count);
        $this->assertSame(0, $unscoped->failure_count);
    }

    public function test_rebuild_is_idempotent(): void
    {
        $org = Organization::factory()->create();
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_1',
            'type' => 'checkout.session.completed',
            'status' => 'processed',
            'processed_at' => now(),
            'organization_id' => $org->id,
        ]);

        $service = app(WebhookSummaryService::class);
        $service->rebuildAll();
        $firstCount = WebhookEventSummary::count();
        $service->rebuildAll();
        $this->assertSame($firstCount, WebhookEventSummary::count());

        $row = WebhookEventSummary::query()
            ->where('organization_scope', (string) $org->id)
            ->where('event_type', 'checkout.session.completed')
            ->first();
        $this->assertSame(1, $row->total_count);
    }

    public function test_last_error_message_is_from_most_recent_failure(): void
    {
        $org = Organization::factory()->create();

        $a = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_old_fail',
            'type' => 'invoice.payment_failed',
            'status' => 'failed',
            'notes' => 'older',
            'organization_id' => $org->id,
        ]);
        $b = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_new_fail',
            'type' => 'invoice.payment_failed',
            'status' => 'failed',
            'notes' => 'newer',
            'organization_id' => $org->id,
        ]);

        \Illuminate\Support\Facades\DB::table('stripe_webhook_events')->where('id', $a->id)->update([
            'created_at' => now()->subHour(),
        ]);
        \Illuminate\Support\Facades\DB::table('stripe_webhook_events')->where('id', $b->id)->update([
            'created_at' => now(),
        ]);

        app(WebhookSummaryService::class)->rebuildAll();

        $row = WebhookEventSummary::query()
            ->where('organization_scope', (string) $org->id)
            ->where('event_type', 'invoice.payment_failed')
            ->first();

        $this->assertSame('newer', $row->last_error_message);
    }

    public function test_rebuild_for_single_organization_scope_leaves_other_orgs_untouched(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_o1',
            'type' => 'customer.subscription.created',
            'status' => 'processed',
            'processed_at' => now(),
            'organization_id' => $org1->id,
        ]);
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_o2',
            'type' => 'customer.subscription.created',
            'status' => 'processed',
            'processed_at' => now(),
            'organization_id' => $org2->id,
        ]);

        $service = app(WebhookSummaryService::class);
        $service->rebuildAll();
        $this->assertSame(2, WebhookEventSummary::count());

        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_o1b',
            'type' => 'customer.subscription.created',
            'status' => 'failed',
            'notes' => 'x',
            'organization_id' => $org1->id,
        ]);

        $service->rebuildForOrganizationScope($org1->id);
        $this->assertSame(2, WebhookEventSummary::count());

        $row1 = WebhookEventSummary::query()
            ->where('organization_scope', (string) $org1->id)
            ->where('event_type', 'customer.subscription.created')
            ->first();
        $this->assertSame(2, $row1->total_count);
        $this->assertSame(1, $row1->failure_count);

        $row2 = WebhookEventSummary::query()
            ->where('organization_scope', (string) $org2->id)
            ->where('event_type', 'customer.subscription.created')
            ->first();
        $this->assertSame(1, $row2->total_count);
    }

    public function test_artisan_rebuild_summaries_command(): void
    {
        Organization::factory()->create();
        StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_cmd',
            'type' => 'ping',
            'status' => 'processed',
            'processed_at' => now(),
            'organization_id' => null,
        ]);

        Artisan::call('webhooks:rebuild-summaries');
        $this->assertDatabaseHas('webhook_event_summaries', [
            'provider' => 'stripe',
            'organization_scope' => 'unscoped',
            'event_type' => 'ping',
            'total_count' => 1,
            'success_count' => 1,
            'failure_count' => 0,
        ]);
    }

    public function test_webhooks_rebuild_summaries_is_scheduled(): void
    {
        $commands = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($event) => $event->command)
            ->filter()
            ->values();

        $this->assertTrue(
            $commands->contains(fn (string $c) => str_contains($c, 'webhooks:rebuild-summaries')),
        );
    }

    public function test_artisan_rebuild_summaries_fails_for_unknown_organization(): void
    {
        $this->artisan('webhooks:rebuild-summaries', ['--organization' => '999999'])
            ->assertFailed();
    }
}
