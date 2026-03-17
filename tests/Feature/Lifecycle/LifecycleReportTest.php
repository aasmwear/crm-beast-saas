<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->expectsOutputToContain('failed_jobs');
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
