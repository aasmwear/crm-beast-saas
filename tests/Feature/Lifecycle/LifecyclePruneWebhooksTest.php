<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LifecyclePruneWebhooksTest extends TestCase
{
    use RefreshDatabase;

    private function insertWebhookEvent(string $eventId, int $daysAgo): void
    {
        $org = Organization::factory()->create();
        $at = now()->subDays($daysAgo);

        DB::table('stripe_webhook_events')->insert([
            'stripe_event_id' => $eventId,
            'type' => 'customer.subscription.updated',
            'status' => 'processed',
            'organization_id' => $org->id,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function test_prune_webhooks_dry_run_does_not_delete(): void
    {
        $this->insertWebhookEvent('evt_prune_dry_1', 100);

        $countBefore = DB::table('stripe_webhook_events')->count();
        $this->assertSame(1, $countBefore);

        $this->artisan('lifecycle:prune-webhooks')
            ->assertSuccessful()
            ->expectsOutputToContain('Candidate rows: 1')
            ->expectsOutputToContain('[DRY-RUN]');

        $countAfter = DB::table('stripe_webhook_events')->count();
        $this->assertSame(1, $countAfter, 'Dry-run must not delete any rows');
    }

    public function test_prune_webhooks_execute_deletes_only_aged_out_events(): void
    {
        $this->insertWebhookEvent('evt_prune_old_1', 100);

        $this->artisan('lifecycle:prune-webhooks', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Candidate rows: 1')
            ->expectsOutputToContain('Deleted: 1');

        $this->assertDatabaseCount('stripe_webhook_events', 0);
    }

    public function test_prune_webhooks_execute_preserves_fresh_rows(): void
    {
        $org = Organization::factory()->create();
        $now = now();

        DB::table('stripe_webhook_events')->insert([
            'stripe_event_id' => 'evt_prune_fresh_1',
            'type' => 'customer.subscription.updated',
            'status' => 'processed',
            'organization_id' => $org->id,
            'created_at' => $now->subDays(10),
            'updated_at' => $now->subDays(10),
        ]);

        $this->insertWebhookEvent('evt_prune_old_1', 100);

        $this->artisan('lifecycle:prune-webhooks', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted: 1');

        $this->assertDatabaseCount('stripe_webhook_events', 1);
        $this->assertDatabaseHas('stripe_webhook_events', ['stripe_event_id' => 'evt_prune_fresh_1']);
    }

    public function test_prune_webhooks_output_is_clear(): void
    {
        $this->artisan('lifecycle:prune-webhooks')
            ->assertSuccessful()
            ->expectsOutputToContain('Lifecycle Prune: stripe_webhook_events')
            ->expectsOutputToContain('Cutoff date:')
            ->expectsOutputToContain('Candidate rows:');
    }

    public function test_prune_webhooks_shows_no_rows_to_prune_when_empty(): void
    {
        $this->artisan('lifecycle:prune-webhooks')
            ->assertSuccessful()
            ->expectsOutputToContain('No rows to prune');
    }
}
