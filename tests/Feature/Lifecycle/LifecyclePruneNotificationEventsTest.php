<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LifecyclePruneNotificationEventsTest extends TestCase
{
    use RefreshDatabase;

    private function insertNotificationEvent(Organization $org, int $createdDaysAgo): int
    {
        $at = now()->subDays($createdDaysAgo);

        return (int) DB::table('notification_events')->insertGetId([
            'organization_id' => $org->id,
            'type' => 'test_event',
            'actor_id' => null,
            'recipient_ids' => json_encode([]),
            'entity' => 'client',
            'entity_id' => 1,
            'payload' => null,
            'read_at' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function test_prune_notification_events_dry_run_does_not_delete(): void
    {
        $org = Organization::factory()->create();
        $this->insertNotificationEvent($org, 100);

        $this->artisan('lifecycle:prune-notification-events')
            ->assertSuccessful()
            ->expectsOutputToContain('Candidate rows: 1')
            ->expectsOutputToContain('[DRY-RUN]');

        $this->assertDatabaseCount('notification_events', 1);
    }

    public function test_prune_notification_events_execute_deletes_aged_rows(): void
    {
        $org = Organization::factory()->create();
        $this->insertNotificationEvent($org, 100);

        $this->artisan('lifecycle:prune-notification-events', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted: 1');

        $this->assertDatabaseCount('notification_events', 0);
    }

    public function test_prune_notification_events_preserves_fresh_rows(): void
    {
        $org = Organization::factory()->create();
        $oldId = $this->insertNotificationEvent($org, 100);
        $freshId = $this->insertNotificationEvent($org, 5);

        $this->artisan('lifecycle:prune-notification-events', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted: 1');

        $this->assertDatabaseMissing('notification_events', ['id' => $oldId]);
        $this->assertDatabaseHas('notification_events', ['id' => $freshId]);
    }

    public function test_prune_notification_events_respects_organization_scope(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $idA = $this->insertNotificationEvent($orgA, 100);
        $idB = $this->insertNotificationEvent($orgB, 100);

        $this->artisan('lifecycle:prune-notification-events', [
            '--execute' => true,
            '--organization' => (string) $orgA->id,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('notification_events', ['id' => $idA]);
        $this->assertDatabaseHas('notification_events', ['id' => $idB]);
    }
}
