<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use App\Models\Organization;
use App\Models\User;
use App\Services\RetentionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LifecycleReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_report_runs_without_error(): void
    {
        $this->artisan('lifecycle:report')
            ->assertSuccessful()
            ->expectsOutputToContain('Data Lifecycle Report')
            ->expectsOutputToContain('read-only');
    }

    public function test_lifecycle_report_shows_all_configured_tables(): void
    {
        $this->artisan('lifecycle:report')
            ->assertSuccessful()
            ->expectsOutputToContain('audit_logs')
            ->expectsOutputToContain('activities')
            ->expectsOutputToContain('stripe_webhook_events')
            ->expectsOutputToContain('failed_jobs')
            ->expectsOutputToContain('notifications')
            ->expectsOutputToContain('notification_events');
    }

    public function test_lifecycle_report_notifications_prune_candidates_require_read(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $created = now()->subDays(200)->toDateTimeString();

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'organization_id' => $org->id,
            'data' => json_encode([]),
            'read_at' => $created,
            'created_at' => $created,
            'updated_at' => $created,
        ]);

        $policy = RetentionPolicy::all()['notifications'];
        $this->assertTrue($policy->pruneRequiresReadAt);
        $this->assertSame(1, DB::table('notifications')->whereNotNull('read_at')->where('created_at', '<', $policy->cutoffDate())->count());

        Artisan::call('lifecycle:report');
        $output = Artisan::output();

        $this->assertStringContainsString('notifications', $output);
        $this->assertStringContainsString('PRUNE candidates', $output, $output);
    }

    public function test_lifecycle_report_detects_aged_out_rows(): void
    {
        $org = Organization::factory()->create();

        DB::table('audit_logs')->insert([
            'organization_id' => $org->id,
            'actor_id' => null,
            'action' => 'test',
            'entity' => 'test',
            'entity_id' => 1,
            'changes' => '{}',
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);

        $this->artisan('lifecycle:report')
            ->assertSuccessful()
            ->expectsOutputToContain('ARCHIVE candidates');
    }

    public function test_lifecycle_report_shows_ok_when_nothing_aged(): void
    {
        $org = Organization::factory()->create();

        DB::table('audit_logs')->insert([
            'organization_id' => $org->id,
            'actor_id' => null,
            'action' => 'test',
            'entity' => 'test',
            'entity_id' => 1,
            'changes' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('lifecycle:report')
            ->assertSuccessful()
            ->expectsOutputToContain('OK');
    }

    public function test_lifecycle_report_is_nondestructive(): void
    {
        $org = Organization::factory()->create();

        DB::table('audit_logs')->insert([
            'organization_id' => $org->id,
            'actor_id' => null,
            'action' => 'lifecycle_test',
            'entity' => 'test',
            'entity_id' => 99,
            'changes' => '{}',
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
        ]);

        $countBefore = DB::table('audit_logs')->count();

        $this->artisan('lifecycle:report')->assertSuccessful();

        $countAfter = DB::table('audit_logs')->count();
        $this->assertSame($countBefore, $countAfter);
    }
}
