<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LifecyclePruneNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function insertDatabaseNotification(
        Organization $org,
        User $user,
        int $createdDaysAgo,
        ?string $readAtDaysAgo,
    ): string {
        $createdAt = now()->subDays($createdDaysAgo);
        $readAt = $readAtDaysAgo !== null
            ? now()->subDays((int) $readAtDaysAgo)->toDateTimeString()
            : null;
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'organization_id' => $org->id,
            'data' => json_encode(['organization_id' => (string) $org->id]),
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }

    public function test_prune_notifications_dry_run_does_not_delete(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $this->insertDatabaseNotification($org, $user, 200, '200');

        $this->artisan('lifecycle:prune-notifications')
            ->assertSuccessful()
            ->expectsOutputToContain('Candidate rows: 1')
            ->expectsOutputToContain('[DRY-RUN]');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_prune_notifications_execute_deletes_only_read_and_aged_rows(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $this->insertDatabaseNotification($org, $user, 200, '200');

        $this->artisan('lifecycle:prune-notifications', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted: 1');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_prune_notifications_preserves_unread_even_when_old(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $id = $this->insertDatabaseNotification($org, $user, 500, null);

        $this->artisan('lifecycle:prune-notifications', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No rows to prune');

        $this->assertDatabaseHas('notifications', ['id' => $id]);
    }

    public function test_prune_notifications_preserves_read_but_recent(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $id = $this->insertDatabaseNotification($org, $user, 10, '5');

        $this->artisan('lifecycle:prune-notifications', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No rows to prune');

        $this->assertDatabaseHas('notifications', ['id' => $id]);
    }

    public function test_prune_notifications_respects_organization_scope(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $user = User::factory()->create();
        $idA = $this->insertDatabaseNotification($orgA, $user, 200, '200');
        $idB = $this->insertDatabaseNotification($orgB, $user, 200, '200');

        $this->artisan('lifecycle:prune-notifications', [
            '--execute' => true,
            '--organization' => (string) $orgA->id,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $idA]);
        $this->assertDatabaseHas('notifications', ['id' => $idB]);
    }

    public function test_prune_notification_commands_are_registered_in_schedule(): void
    {
        $commands = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($event) => $event->command)
            ->filter()
            ->values();

        $this->assertTrue(
            $commands->contains(fn (string $c) => str_contains($c, 'lifecycle:prune-notifications') && str_contains($c, '--execute')),
        );
        $this->assertTrue(
            $commands->contains(fn (string $c) => str_contains($c, 'lifecycle:prune-notification-events') && str_contains($c, '--execute')),
        );
    }
}
