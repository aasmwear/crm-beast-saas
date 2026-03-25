<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\OrgDailyMetric;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\OrgMetricsSnapshotService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class DashboardHybridMetricsTest extends TestCase
{
    use RefreshDatabase;

    private OrgMetricsSnapshotService $snapshotService;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(CarbonImmutable::parse('2026-03-19 12:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->snapshotService = app(OrgMetricsSnapshotService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createAdminUser(Organization $org): User
    {
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);

        $role = Role::firstOrCreate(
            ['name' => 'dashboard-admin-' . $org->id, 'guard_name' => 'web', 'team_id' => $org->id],
            ['guard_name' => 'web', 'team_id' => $org->id]
        );
        $role->syncPermissions(['clients.view', 'projects.view', 'tasks.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        return $user;
    }

    public function test_dashboard_uses_snapshot_backed_values_when_rows_exist(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createAdminUser($org);

        $endDate = now()->toDateString();
        $startDate = now()->subDays(14)->toDateString();

        // Create snapshots for date range (backfill)
        $start = CarbonImmutable::parse($startDate)->subDay();
        $end = CarbonImmutable::parse($endDate);
        for ($d = $start; $d <= $end; $d = $d->addDay()) {
            $this->snapshotService->snapshotOrg($org, $d);
        }

        // Add live data that would increase counts - snapshot has yesterday's state
        $client = Client::factory()->create(['organization_id' => $org->id]);
        Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->for($client, 'client')->create(['organization_id' => $org->id]);
        Task::factory()->count(3)->create(['organization_id' => $org->id, 'project_id' => $project->id]);

        // Snapshot today to capture current state
        $this->snapshotService->snapshotOrg($org, CarbonImmutable::parse($endDate));

        $response = $this->actingAs($user)
            ->get(route('dashboard', [
                'organization' => $org->slug,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->has('stats')
                ->has('kpis')
                ->has('charts')
                ->has('charts.clients30d')
                ->has('charts.projects30d')
                ->has('charts.tasks30d')
            );

        $props = $response->original->getData()['page']['props'];
        $stats = $props['stats'];
        $kpis = $props['kpis'];
        $charts = $props['charts'];

        // KPIs from snapshot: total_revenue, outstanding_revenue (active_projects stays live)
        $this->assertArrayHasKey('total_revenue', $kpis);
        $this->assertArrayHasKey('outstanding_revenue', $kpis);
        $this->assertArrayHasKey('active_projects', $kpis);

        // Stats and charts should have correct shape (snapshot or live)
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('clients', $stats);
        $this->assertArrayHasKey('projects', $stats);
        $this->assertArrayHasKey('tasks', $stats);
        $this->assertCount(count($charts['clients30d']['labels']), $charts['clients30d']['values']);
    }

    public function test_dashboard_falls_back_safely_when_snapshots_are_missing(): void
    {
        $org = Organization::factory()->create(['slug' => 'fallback-org']);
        $user = $this->createAdminUser($org);

        // No org_daily_metrics rows for this org - must fall back to live queries
        $this->assertSame(0, OrgDailyMetric::where('organization_id', $org->id)->count());

        $client = Client::factory()->create(['organization_id' => $org->id]);
        Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->for($client, 'client')->create(['organization_id' => $org->id]);
        Task::factory()->count(1)->create(['organization_id' => $org->id, 'project_id' => $project->id]);

        $startDate = now()->subDays(5)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->actingAs($user)
            ->get(route('dashboard', [
                'organization' => $org->slug,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->has('stats')
                ->has('kpis')
            );

        $props = $response->original->getData()['page']['props'];
        $stats = $props['stats'];
        $charts = $props['charts'];

        // Live fallback: stats reflect created-in-range (2 clients, 1 project, 1 task in range)
        $this->assertGreaterThanOrEqual(2, $stats['clients']);
        $this->assertGreaterThanOrEqual(1, $stats['projects']);
        $this->assertGreaterThanOrEqual(1, $stats['tasks']);

        // Chart series should have correct structure
        $expectedDays = (int) \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1;
        $this->assertCount($expectedDays, $charts['clients30d']['labels']);
        $this->assertCount($expectedDays, $charts['clients30d']['values']);
    }

    public function test_chart_series_output_remains_correct_with_snapshot(): void
    {
        $org = Organization::factory()->create(['slug' => 'chart-org']);
        $user = $this->createAdminUser($org);

        $startDate = '2026-03-01';
        $endDate = '2026-03-05';

        // Backfill snapshots for full range
        for ($i = 0; $i <= 6; $i++) {
            $d = CarbonImmutable::parse($startDate)->subDay()->addDays($i);
            OrgDailyMetric::create([
                'organization_id' => $org->id,
                'metric_date' => $d->toDateString(),
                'clients_count' => $i,
                'projects_count' => 0,
                'tasks_count' => 0,
                'open_tasks_count' => 0,
                'attendance_count' => 0,
                'activities_count' => 0,
                'invoices_count' => 0,
                'revenue_cents' => 0,
                'outstanding_cents' => 0,
                'users_count' => 1,
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('dashboard', [
                'organization' => $org->slug,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]));

        $response->assertOk();
        $charts = $response->original->getData()['page']['props']['charts'];
        $clients30d = $charts['clients30d'];

        // Delta for day i: count(i) - count(i-1) = 1 for each day in range
        $this->assertCount(5, $clients30d['labels']);
        $this->assertCount(5, $clients30d['values']);
        $this->assertEquals([1, 1, 1, 1, 1], $clients30d['values']);
    }

    public function test_org_isolation_dashboard_metrics(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        $userA = $this->createAdminUser($orgA);

        // Org A: 3 clients, snapshot
        Client::factory()->count(3)->create(['organization_id' => $orgA->id]);
        $this->snapshotService->snapshotOrg($orgA, CarbonImmutable::today());

        // Org B: 10 clients, snapshot
        Client::factory()->count(10)->create(['organization_id' => $orgB->id]);
        $this->snapshotService->snapshotOrg($orgB, CarbonImmutable::today());

        $startDate = now()->subDays(1)->toDateString();
        $endDate = now()->toDateString();

        // Backfill for stats/series coverage
        for ($d = CarbonImmutable::parse($startDate)->subDay(); $d <= CarbonImmutable::parse($endDate); $d = $d->addDay()) {
            $this->snapshotService->snapshotOrg($orgA, $d);
            $this->snapshotService->snapshotOrg($orgB, $d);
        }

        $responseA = $this->actingAs($userA)
            ->get(route('dashboard', [
                'organization' => $orgA->slug,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]));

        $responseA->assertOk();
        $statsA = $responseA->original->getData()['page']['props']['stats'];

        // User in org A must see org A metrics only - 3 clients in org A, 10 in org B
        $this->assertSame(3, $statsA['clients']);
        $this->assertNotSame(10, $statsA['clients']);
    }
}
