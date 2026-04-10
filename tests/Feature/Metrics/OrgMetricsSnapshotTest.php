<?php

declare(strict_types=1);

namespace Tests\Feature\Metrics;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\OrgMetricsSnapshotService;
use App\Services\Tenancy\TenantTierService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OrgMetricsSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private OrgMetricsSnapshotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgMetricsSnapshotService::class);
    }

    public function test_snapshot_creates_row_for_org_date(): void
    {
        $org = Organization::factory()->create();
        $date = CarbonImmutable::parse('2026-03-18');

        $metric = $this->service->snapshotOrg($org, $date);

        $this->assertDatabaseHas('org_daily_metrics', [
            'organization_id' => $org->id,
            'metric_date' => '2026-03-18',
        ]);
        $this->assertSame(0, $metric->clients_count);
        $this->assertSame(0, $metric->projects_count);
    }

    public function test_snapshot_upsert_updates_existing_row(): void
    {
        $org = Organization::factory()->create();
        $date = CarbonImmutable::parse('2026-03-18');

        $first = $this->service->snapshotOrg($org, $date);
        $this->assertSame(0, $first->clients_count);

        // Snapshot counts use created_at <= metric end-of-day; pin timestamps to metric_date.
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $asOf = $date->endOfDay();
        $client->forceFill([
            'created_at' => $asOf,
            'updated_at' => $asOf,
        ])->saveQuietly();

        $second = $this->service->snapshotOrg($org, $date);
        $this->assertSame(1, $second->clients_count);

        $this->assertDatabaseCount('org_daily_metrics', 1);
    }

    public function test_snapshot_counts_correct_metrics(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user);

        $clients = Client::factory()->count(3)->create(['organization_id' => $org->id]);
        $project = Project::create([
            'organization_id' => $org->id,
            'client_id' => $clients->first()->id,
            'title' => 'Test Project',
            'status' => 'active',
        ]);
        Task::create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'title' => 'Open task',
            'status' => 'in_progress',
        ]);
        Task::create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'title' => 'Completed task',
            'status' => 'completed',
        ]);
        $today = now()->toDateString();
        Invoice::create([
            'organization_id' => $org->id,
            'client_id' => $clients->first()->id,
            'number' => 'INV-001',
            'issue_date' => $today,
            'due_date' => $today,
            'status' => 'paid',
            'total_cents' => 5000,
        ]);
        Invoice::create([
            'organization_id' => $org->id,
            'client_id' => $clients->first()->id,
            'number' => 'INV-002',
            'issue_date' => $today,
            'due_date' => $today,
            'status' => 'sent',
            'total_cents' => 3000,
        ]);
        Activity::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'description' => 'test activity',
            'subject_type' => 'App\\Models\\Project',
            'subject_id' => $project->id,
        ]);

        $date = CarbonImmutable::now();
        $metric = $this->service->snapshotOrg($org, $date);

        $this->assertSame(3, $metric->clients_count);
        $this->assertSame(1, $metric->projects_count);
        $this->assertSame(2, $metric->tasks_count);
        $this->assertSame(1, $metric->open_tasks_count);
        $this->assertSame(2, $metric->invoices_count);
        $this->assertSame(5000, $metric->revenue_cents);
        $this->assertSame(3000, $metric->outstanding_cents);
        $this->assertSame(1, $metric->activities_count);
        $this->assertSame(1, $metric->users_count);
    }

    public function test_snapshot_isolates_orgs(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Client::factory()->count(5)->create(['organization_id' => $orgA->id]);
        Client::factory()->count(2)->create(['organization_id' => $orgB->id]);

        $date = CarbonImmutable::now();
        $metricA = $this->service->snapshotOrg($orgA, $date);
        $metricB = $this->service->snapshotOrg($orgB, $date);

        $this->assertSame(5, $metricA->clients_count);
        $this->assertSame(2, $metricB->clients_count);
    }

    public function test_snapshot_all_orgs_creates_rows_for_every_org(): void
    {
        Organization::factory()->count(3)->create();
        $date = CarbonImmutable::parse('2026-03-18');

        $count = $this->service->snapshotAllOrgs($date);

        $this->assertSame(3, $count);
        $this->assertDatabaseCount('org_daily_metrics', 3);
    }

    public function test_snapshot_excludes_soft_deleted_clients(): void
    {
        $org = Organization::factory()->create();
        $activeClient = Client::factory()->create(['organization_id' => $org->id]);
        $deletedClient = Client::factory()->create(['organization_id' => $org->id]);
        $deletedClient->delete();

        $metric = $this->service->snapshotOrg($org, CarbonImmutable::now());

        $this->assertSame(1, $metric->clients_count);
    }

    public function test_command_runs_successfully(): void
    {
        Organization::factory()->create();

        $this->artisan('metrics:snapshot-orgs', ['--date' => '2026-03-18'])
            ->assertSuccessful()
            ->expectsOutputToContain('Snapshot created for 1 organization')
            ->expectsOutputToContain('Tenant tiers recalculated');

        $this->assertDatabaseCount('org_daily_metrics', 1);
    }

    public function test_command_supports_single_org_filter(): void
    {
        $org = Organization::factory()->create();
        Organization::factory()->create();

        $this->artisan('metrics:snapshot-orgs', [
            '--date' => '2026-03-18',
            '--org' => $org->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Snapshot created for org #' . $org->id)
            ->expectsOutputToContain('Tenant tier refreshed');

        $this->assertDatabaseCount('org_daily_metrics', 1);
        $this->assertDatabaseHas('org_daily_metrics', ['organization_id' => $org->id]);
    }

    public function test_command_defaults_to_yesterday(): void
    {
        $org = Organization::factory()->create();

        $this->artisan('metrics:snapshot-orgs')
            ->assertSuccessful()
            ->expectsOutputToContain(CarbonImmutable::yesterday()->toDateString())
            ->expectsOutputToContain('Tenant tiers recalculated');

        $this->assertDatabaseHas('org_daily_metrics', [
            'organization_id' => $org->id,
            'metric_date' => CarbonImmutable::yesterday()->toDateString(),
        ]);
    }

    public function test_command_fails_for_nonexistent_org(): void
    {
        $this->artisan('metrics:snapshot-orgs', ['--org' => 99999])
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    public function test_metrics_snapshot_recalculates_tenant_tier_after_snapshot(): void
    {
        $org = Organization::factory()->create();
        Client::factory()->count(25)->create(['organization_id' => $org->id]);

        $this->artisan('metrics:snapshot-orgs', ['--date' => '2026-03-18'])
            ->assertSuccessful();

        $org->refresh();
        $this->assertSame(TenantTierService::TIER_MEDIUM, $org->tier);
    }
}
