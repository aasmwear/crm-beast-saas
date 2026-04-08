<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LifecycleArchiveWarmTablesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_archive_audit_logs_dry_run_does_not_move_rows(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00'));
        $org = Organization::factory()->create();
        $this->insertOldAuditLog($org->id);

        $this->artisan('lifecycle:archive-audit-logs')->assertSuccessful();

        $this->assertSame(1, DB::table('audit_logs')->count());
        $this->assertSame(0, DB::table('audit_logs_archive')->count());
    }

    public function test_archive_audit_logs_execute_moves_only_aged_rows(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00'));
        $org = Organization::factory()->create();
        $oldId = $this->insertOldAuditLog($org->id);
        $freshId = $this->insertFreshAuditLog($org->id);

        $this->artisan('lifecycle:archive-audit-logs', ['--execute' => true])->assertSuccessful();

        $this->assertNull(DB::table('audit_logs')->where('id', $oldId)->first());
        $this->assertNotNull(DB::table('audit_logs_archive')->where('id', $oldId)->first());
        $this->assertNotNull(DB::table('audit_logs')->where('id', $freshId)->first());
        $this->assertNull(DB::table('audit_logs_archive')->where('id', $freshId)->first());
    }

    public function test_archive_audit_logs_preserves_organization_id_in_archive(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00'));
        $org = Organization::factory()->create();
        $oldId = $this->insertOldAuditLog($org->id);

        $this->artisan('lifecycle:archive-audit-logs', ['--execute' => true])->assertSuccessful();

        $archived = DB::table('audit_logs_archive')->where('id', $oldId)->first();
        $this->assertNotNull($archived);
        $this->assertSame($org->id, (int) $archived->organization_id);
        $this->assertSame('create', $archived->action);
    }

    public function test_archive_audit_logs_second_run_does_not_duplicate_archive(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00'));
        $org = Organization::factory()->create();
        $this->insertOldAuditLog($org->id);

        $this->artisan('lifecycle:archive-audit-logs', ['--execute' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('audit_logs_archive')->count());

        $this->artisan('lifecycle:archive-audit-logs', ['--execute' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('audit_logs_archive')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_archive_audit_logs_reconciles_hot_row_when_archive_already_has_id(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00'));
        $org = Organization::factory()->create();
        $oldId = $this->insertOldAuditLog($org->id);

        $this->artisan('lifecycle:archive-audit-logs', ['--execute' => true])->assertSuccessful();

        DB::table('audit_logs')->insert([
            'id' => $oldId,
            'organization_id' => $org->id,
            'actor_id' => null,
            'action' => 'create',
            'entity' => 'client',
            'entity_id' => 1,
            'changes' => json_encode([]),
            'created_at' => now()->subDays(120)->toDateTimeString(),
            'updated_at' => now()->subDays(120)->toDateTimeString(),
        ]);

        $this->artisan('lifecycle:archive-audit-logs', ['--execute' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('audit_logs_archive')->where('id', $oldId)->count());
        $this->assertNull(DB::table('audit_logs')->where('id', $oldId)->first());
    }

    public function test_archive_audit_logs_respects_organization_scope(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00'));
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $idA = $this->insertOldAuditLog($orgA->id);
        $idB = $this->insertOldAuditLog($orgB->id);

        $this->artisan('lifecycle:archive-audit-logs', [
            '--execute' => true,
            '--organization' => (string) $orgA->id,
        ])->assertSuccessful();

        $this->assertNull(DB::table('audit_logs')->where('id', $idA)->first());
        $this->assertNotNull(DB::table('audit_logs_archive')->where('id', $idA)->first());
        $this->assertNotNull(DB::table('audit_logs')->where('id', $idB)->first());
        $this->assertNull(DB::table('audit_logs_archive')->where('id', $idB)->first());
    }

    public function test_archive_activities_dry_run_does_not_move_rows(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00'));
        $org = Organization::factory()->create();
        $this->insertOldActivity($org);

        $this->artisan('lifecycle:archive-activities')->assertSuccessful();

        $this->assertSame(1, DB::table('activities')->count());
        $this->assertSame(0, DB::table('activities_archive')->count());
    }

    public function test_archive_activities_execute_preserves_org_and_subject(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00'));
        $org = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);
        $oldId = $this->insertOldActivity($org, $project);

        $this->artisan('lifecycle:archive-activities', ['--execute' => true])->assertSuccessful();

        $archived = DB::table('activities_archive')->where('id', $oldId)->first();
        $this->assertNotNull($archived);
        $this->assertSame($org->id, (int) $archived->organization_id);
        $this->assertSame(Project::class, $archived->subject_type);
        $this->assertSame($project->id, (int) $archived->subject_id);
        $this->assertNull(DB::table('activities')->where('id', $oldId)->first());
    }

    public function test_archive_activities_fresh_row_stays_hot(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00'));
        $org = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);
        $oldId = $this->insertOldActivity($org, $project);
        $fresh = Activity::query()->create([
            'organization_id' => $org->id,
            'user_id' => null,
            'description' => 'recent',
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'properties' => null,
        ]);

        $this->artisan('lifecycle:archive-activities', ['--execute' => true])->assertSuccessful();

        $this->assertNull(DB::table('activities')->where('id', $oldId)->first());
        $this->assertNotNull(DB::table('activities')->where('id', $fresh->id)->first());
    }

    public function test_lifecycle_archive_commands_are_registered_in_schedule(): void
    {
        $commands = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($event) => $event->command)
            ->filter()
            ->values();

        $this->assertTrue(
            $commands->contains(fn (string $c) => str_contains($c, 'lifecycle:archive-audit-logs') && str_contains($c, '--execute')),
            'lifecycle:archive-audit-logs --execute should be on the console schedule.',
        );

        $this->assertTrue(
            $commands->contains(fn (string $c) => str_contains($c, 'lifecycle:archive-activities') && str_contains($c, '--execute')),
            'lifecycle:archive-activities --execute should be on the console schedule.',
        );
    }

    private function insertOldAuditLog(int $organizationId): int
    {
        $ts = now()->subDays(91)->toDateTimeString();

        return (int) DB::table('audit_logs')->insertGetId([
            'organization_id' => $organizationId,
            'actor_id' => null,
            'action' => 'create',
            'entity' => 'client',
            'entity_id' => 1,
            'changes' => json_encode([]),
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
    }

    private function insertFreshAuditLog(int $organizationId): int
    {
        $ts = now()->subDays(5)->toDateTimeString();

        return (int) DB::table('audit_logs')->insertGetId([
            'organization_id' => $organizationId,
            'actor_id' => null,
            'action' => 'update',
            'entity' => 'client',
            'entity_id' => 2,
            'changes' => json_encode(['a' => 1]),
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
    }

    private function insertOldActivity(Organization $org, ?Project $project = null): int
    {
        if ($project === null) {
            $client = Client::factory()->create(['organization_id' => $org->id]);
            $project = Project::factory()->create([
                'organization_id' => $org->id,
                'client_id' => $client->id,
            ]);
        }

        $id = (int) DB::table('activities')->insertGetId([
            'organization_id' => $org->id,
            'user_id' => null,
            'description' => 'old activity',
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'properties' => null,
            'created_at' => now()->subDays(91)->toDateTimeString(),
            'updated_at' => now()->subDays(91)->toDateTimeString(),
        ]);

        return $id;
    }
}
