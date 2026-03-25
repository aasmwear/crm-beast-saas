<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\OrgDailyMetric;
use App\Models\Project;
use App\Models\Task;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the main dashboard. Employees get a "My Work" dashboard; Admins get the full analytics dashboard.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The Inertia response.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $org = $request->route('organization');
        $orgId = $org ? (is_object($org) ? (int) $org->id : null) : $user?->active_organization_id;

        if (! $org && $orgId) {
            $org = DB::table('organizations')->select('id', 'name', 'slug')
                ->where('id', $orgId)
                ->first();
        }
        if (! $org) {
            $org = DB::table('organizations')->select('id', 'name', 'slug')
                ->orderBy('id')
                ->first();
        }
        $orgId = $org ? (int) ($org->id ?? $org['id'] ?? 0) : null;

        // Route to Employee dashboard if user is Employee (and not admin)
        if ($user && $orgId && $this->isEmployee($user, $orgId)) {
            return $this->employeeDashboard($request, $user, $org, $orgId);
        }

        return $this->adminDashboard($request, $user, $org, $orgId);
    }

    /**
     * Check if the user is an Employee (non-admin) in the given org.
     * ResolveTenant middleware sets the Spatie team context before this runs.
     */
    private function isEmployee($user, int $orgId): bool
    {
        if ($user->is_super_admin ?? false) {
            return false;
        }

        if (! $user->hasRole('Employee')) {
            return false;
        }

        return ! $user->hasAnyRole(['Super Admin', 'Manager', 'Owner', 'Admin']);
    }

    /**
     * Employee "My Work" dashboard: tasks, projects, activity.
     */
    private function employeeDashboard(Request $request, $user, $org, int $orgId): Response
    {
        $userId = $user->id;

        // My tasks: assigned to user, status not completed, ordered by due date
        $myTasks = Task::query()
            ->with('project:id,title')
            ->where('organization_id', $orgId)
            ->whereJsonContains('assignees', $userId)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("lower(trim(coalesce(status,''))) not in ('completed','done','approved')");
            })
            ->orderByRaw('due_date asc nulls last')
            ->limit(50)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'due_date' => $t->due_date?->toDateString(),
                'priority' => $t->priority,
                'project' => $t->project ? ['id' => $t->project->id, 'title' => $t->project->title] : null,
            ])
            ->values()
            ->all();

        // My projects: projects the user is assigned to (PM or has tasks), excluding completed
        $myProjects = Project::query()
            ->where('organization_id', $orgId)
            ->where(function ($q) use ($userId) {
                $q->where('project_manager_id', $userId)
                    ->orWhereHas('tasks', fn ($qt) => $qt->whereJsonContains('assignees', $userId));
            })
            ->whereRaw("lower(trim(coalesce(status,''))) not in ('completed','done','closed')")
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'title', 'status', 'updated_at'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'status' => $p->status,
                'updated_at' => $p->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        // Recent activity filtered to their projects
        $projectIds = collect($myProjects)->pluck('id')->all();
        $recentActivity = [];
        if (! empty($projectIds)) {
            $recentActivity = Activity::query()
                ->with('user:id,name')
                ->forOrganization($orgId)
                ->where(function ($q) use ($projectIds) {
                    $q->whereHasMorph('subject', [Project::class], fn ($q) => $q->whereIn('id', $projectIds))
                        ->orWhereHasMorph('subject', [Task::class], fn ($q) => $q->whereIn('project_id', $projectIds));
                })
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'description' => $a->description,
                    'properties' => $a->properties,
                    'created_at' => $a->created_at?->toIso8601String(),
                    'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
                ])
                ->values()
                ->all();
        }

        $tasksDueToday = collect($myTasks)->filter(fn ($t) => ($t['due_date'] ?? null) === now()->toDateString())->count();

        // Current attendance (for clock widget)
        $currentAttendance = Attendance::query()
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->whereNull('clock_out_at')
            ->orderByDesc('clock_in_at')
            ->first();

        return Inertia::render('Dashboard/Employee', [
            'org' => $org,
            'my_tasks' => $myTasks,
            'my_projects' => $myProjects,
            'recent_activity' => $recentActivity,
            'tasks_due_today' => $tasksDueToday,
            'currentAttendance' => $currentAttendance ? [
                'id' => $currentAttendance->id,
                'clock_in_at' => $currentAttendance->clock_in_at?->toIso8601String(),
                'clock_out_at' => $currentAttendance->clock_out_at?->toIso8601String(),
                'status' => $currentAttendance->clock_out_at ? 'clocked_out' : 'clocked_in',
            ] : null,
        ]);
    }

    /**
     * Admin dashboard with analytics KPIs and charts.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The Inertia response.
     */
    private function adminDashboard(Request $request, $user, $org, ?int $orgId): Response
    {
        $org = $request->route('organization');
        $orgId = $org ? (is_object($org) ? (int) $org->id : null) : $user?->active_organization_id;

        if (! $org && $orgId) {
            $org = DB::table('organizations')->select('id', 'name', 'slug')
                ->where('id', $orgId)
                ->first();
        }
        if (! $org) {
            $org = DB::table('organizations')->select('id', 'name', 'slug')
                ->orderBy('id')
                ->first();
        }
        $orgId = $org ? (int) ($org->id ?? $org['id'] ?? 0) : null;

        // 1. KPIs: total_revenue, outstanding_revenue, active_projects
        // Hybrid: use org_daily_metrics when snapshot exists; fallback to live queries
        $kpiFromSnapshot = $this->getKpisFromSnapshot($orgId);
        $totalRevenue = $kpiFromSnapshot !== null
            ? $kpiFromSnapshot['total_revenue']
            : $this->liveTotalRevenue($orgId);
        $outstandingRevenue = $kpiFromSnapshot !== null
            ? $kpiFromSnapshot['outstanding_revenue']
            : $this->liveOutstandingRevenue($orgId);
        // active_projects is NOT in org_daily_metrics; always live
        $activeProjects = Project::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->whereRaw("lower(trim(status)) in ('in progress', 'in_progress', 'active', 'planned')")
            ->count();

        // 2. Monthly revenue (last 6 months) - Labels: Jan, Feb; Data: revenue in cents
        $monthlyRevenue = $this->monthlyRevenueSeries($orgId);

        // 3. Project status distribution - Active, Completed, On Hold
        $projectStatusRows = Project::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $projectStatus = [
            'Active' => 0,
            'Completed' => 0,
            'On Hold' => 0,
        ];

        foreach ($projectStatusRows as $row) {
            $s = strtolower(trim((string) $row->status));
            if (in_array($s, ['in progress', 'in_progress', 'active', 'planned'], true)) {
                $projectStatus['Active'] += (int) $row->count;
            } elseif (in_array($s, ['completed', 'done', 'closed'], true)) {
                $projectStatus['Completed'] += (int) $row->count;
            } else {
                $projectStatus['On Hold'] += (int) $row->count;
            }
        }

        $projectStatusChart = [
            'labels' => array_keys($projectStatus),
            'values' => array_values($projectStatus),
        ];

        // 4. Recent Activity (from Activity model - projects, tasks, invoices in this org)
        $recentActivities = [];
        if ($orgId) {
            $recentActivities = Activity::query()
                ->with('user:id,name')
                ->forOrganization($orgId)
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'description' => $a->description,
                    'properties' => $a->properties,
                    'created_at' => $a->created_at?->toIso8601String(),
                    'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
                ])
                ->values()
                ->all();
        }

        // Legacy stats (for backwards compatibility with existing dashboard components)
        $defaultStart = now()->subDays(29)->toDateString();
        $defaultEnd = now()->toDateString();
        $startDate = $request->query('start_date', $defaultStart);
        $endDate = $request->query('end_date', $defaultEnd);
        $dbStartDate = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
        $dbEndDate = Carbon::parse($endDate)->endOfDay()->toDateTimeString();

        $useStatsFromSnapshot = $orgId && $this->hasSufficientSnapshotCoverageForStats($orgId, $startDate, $endDate);
        if ($useStatsFromSnapshot) {
            $statsFromSnapshot = $this->getStatsFromSnapshot($orgId, $startDate, $endDate);
            $stats = [
                'clients' => $statsFromSnapshot['clients'],
                'projects' => $statsFromSnapshot['projects'],
                'tasks' => $statsFromSnapshot['tasks'],
                'total_revenue' => $totalRevenue,
                'outstanding_revenue' => $outstandingRevenue,
                'active_projects' => $activeProjects,
            ];
        } else {
            $stats = [
                'clients' => Client::query()
                    ->whereBetween('created_at', [$dbStartDate, $dbEndDate])
                    ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                    ->count(),
                'projects' => Project::query()
                    ->whereBetween('created_at', [$dbStartDate, $dbEndDate])
                    ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                    ->count(),
                'tasks' => Task::query()
                    ->whereBetween('created_at', [$dbStartDate, $dbEndDate])
                    ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                    ->count(),
                'total_revenue' => $totalRevenue,
                'outstanding_revenue' => $outstandingRevenue,
                'active_projects' => $activeProjects,
            ];
        }

        $useSeriesFromSnapshot = $orgId && $this->hasSufficientSnapshotCoverageForSeries($orgId, $startDate, $endDate);
        if ($useSeriesFromSnapshot) {
            $clients30d = $this->seriesFromSnapshot($orgId, 'clients', $startDate, $endDate);
            $projects30d = $this->seriesFromSnapshot($orgId, 'projects', $startDate, $endDate);
            $tasks30d = $this->seriesFromSnapshot($orgId, 'tasks', $startDate, $endDate);
        } else {
            $clients30d = $this->seriesByDay('clients', $orgId, $startDate, $endDate);
            $projects30d = $this->seriesByDay('projects', $orgId, $startDate, $endDate);
            $tasks30d = $this->seriesByDay('tasks', $orgId, $startDate, $endDate);
        }

        $workloadRows = Task::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$dbStartDate, $dbEndDate])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->groupBy('status')
            ->pluck('count', 'status');

        $workload = [
            'labels' => $workloadRows->keys()->all(),
            'values' => $workloadRows->values()->all(),
        ];

        $tasksByAssignee = [
            'labels' => ['Alice', 'Bob', 'Charlie'],
            'values' => [15, 8, 22],
        ];

        $deadlines = [
            ['id' => 1, 'title' => 'Project Alpha Final Review', 'due' => 'Oct 30'],
            ['id' => 2, 'title' => 'Client Y Invoicing', 'due' => 'Nov 01'],
        ];

        $currentAttendance = null;
        if ($user && $orgId) {
            $currentAttendance = Attendance::query()
                ->where('organization_id', $orgId)
                ->where('user_id', $user->id)
                ->whereNull('clock_out_at')
                ->orderByDesc('clock_in_at')
                ->first();
        }

        return Inertia::render('Dashboard/Index', [
            'org' => $org,
            'stats' => $stats,
            'kpis' => [
                'total_revenue' => $totalRevenue,
                'outstanding_revenue' => $outstandingRevenue,
                'active_projects' => $activeProjects,
            ],
            'charts' => [
                'clients30d' => $clients30d,
                'projects30d' => $projects30d,
                'tasks30d' => $tasks30d,
                'workload' => $workload,
                'tasksByAssignee' => $tasksByAssignee,
                'monthly_revenue' => $monthlyRevenue,
                'project_status' => $projectStatusChart,
            ],
            'activities' => $recentActivities,
            'recent' => collect($recentActivities)->take(5)->map(fn ($a) => [
                'id' => $a['id'],
                'when' => Carbon::parse($a['created_at'] ?? null)->diffForHumans(),
                'message' => ($a['user']['name'] ?? 'Someone') . ' ' . $a['description'],
            ])->values()->all(),
            'deadlines' => $deadlines,
            'currentAttendance' => $currentAttendance,
            'rawStartDate' => $startDate,
            'rawEndDate' => $endDate,
            'startDateFormatted' => Carbon::parse($startDate)->format('M d'),
            'endDateFormatted' => Carbon::parse($endDate)->format('M d, Y'),
        ]);
    }

    /**
     * Whether org_daily_metrics has at least one row for this org (for KPI revenue/outstanding).
     */
    private function hasSufficientSnapshotCoverageForKpis(?int $orgId): bool
    {
        if (! $orgId) {
            return false;
        }

        return OrgDailyMetric::query()
            ->where('organization_id', $orgId)
            ->exists();
    }

    /**
     * KPIs from snapshot: total_revenue, outstanding_revenue.
     * Uses latest snapshot (most recent metric_date). Returns null when no snapshot exists.
     *
     * @return array{total_revenue: int, outstanding_revenue: int}|null
     */
    private function getKpisFromSnapshot(?int $orgId): ?array
    {
        if (! $orgId || ! $this->hasSufficientSnapshotCoverageForKpis($orgId)) {
            return null;
        }

        $latest = OrgDailyMetric::query()
            ->where('organization_id', $orgId)
            ->orderByDesc('metric_date')
            ->first(['revenue_cents', 'outstanding_cents']);

        return $latest ? [
            'total_revenue' => $latest->revenue_cents,
            'outstanding_revenue' => $latest->outstanding_cents,
        ] : null;
    }

    private function liveTotalRevenue(?int $orgId): int
    {
        return (int) Invoice::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->whereRaw('lower(status) = ?', ['paid'])
            ->sum('total_cents');
    }

    private function liveOutstandingRevenue(?int $orgId): int
    {
        return (int) Invoice::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where(function ($q) {
                $q->whereRaw('lower(trim(status)) = ?', ['sent'])
                    ->orWhereRaw('lower(trim(status)) = ?', ['overdue']);
            })
            ->sum('total_cents');
    }

    /**
     * Whether snapshot coverage is sufficient for "created in range" stats.
     * Needs snapshot for end_date; start_date-1 may be missing (we assume 0).
     */
    private function hasSufficientSnapshotCoverageForStats(?int $orgId, string $startDate, string $endDate): bool
    {
        if (! $orgId) {
            return false;
        }

        return OrgDailyMetric::query()
            ->where('organization_id', $orgId)
            ->where('metric_date', $endDate)
            ->exists();
    }

    /**
     * Stats (clients, projects, tasks created in range) derived from org_daily_metrics.
     * created_in_range = count(end_date) - count(start_date - 1). Missing start-1 treated as 0.
     *
     * @return array{clients: int, projects: int, tasks: int}
     */
    private function getStatsFromSnapshot(int $orgId, string $startDate, string $endDate): array
    {
        $endMetric = OrgDailyMetric::query()
            ->where('organization_id', $orgId)
            ->where('metric_date', $endDate)
            ->first(['clients_count', 'projects_count', 'tasks_count']);

        $startMinusOne = Carbon::parse($startDate)->subDay()->toDateString();
        $startMetric = OrgDailyMetric::query()
            ->where('organization_id', $orgId)
            ->where('metric_date', $startMinusOne)
            ->first(['clients_count', 'projects_count', 'tasks_count']);

        $end = $endMetric ? [
            'clients' => $endMetric->clients_count,
            'projects' => $endMetric->projects_count,
            'tasks' => $endMetric->tasks_count,
        ] : ['clients' => 0, 'projects' => 0, 'tasks' => 0];

        $start = $startMetric ? [
            'clients' => $startMetric->clients_count,
            'projects' => $startMetric->projects_count,
            'tasks' => $startMetric->tasks_count,
        ] : ['clients' => 0, 'projects' => 0, 'tasks' => 0];

        return [
            'clients' => max(0, $end['clients'] - $start['clients']),
            'projects' => max(0, $end['projects'] - $start['projects']),
            'tasks' => max(0, $end['tasks'] - $start['tasks']),
        ];
    }

    /**
     * Whether snapshot coverage is sufficient for daily series (clients30d, projects30d, tasks30d).
     * Needs consecutive rows from (start_date - 1) through end_date to compute daily deltas.
     */
    private function hasSufficientSnapshotCoverageForSeries(?int $orgId, string $startDate, string $endDate): bool
    {
        if (! $orgId) {
            return false;
        }

        $start = Carbon::parse($startDate)->subDay();
        $end = Carbon::parse($endDate);
        $daysNeeded = $start->diffInDays($end) + 1; // rows from (start_date-1) through end_date inclusive

        $count = OrgDailyMetric::query()
            ->where('organization_id', $orgId)
            ->whereBetween('metric_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        return $count >= $daysNeeded;
    }

    /**
     * Build daily series from org_daily_metrics. Delta for day D = count(D) - count(D-1).
     *
     * @param  'clients'|'projects'|'tasks'  $metric
     * @return array{labels: list<string>, values: list<int>}
     */
    private function seriesFromSnapshot(int $orgId, string $metric, string $startDate, string $endDate): array
    {
        $column = match ($metric) {
            'clients' => 'clients_count',
            'projects' => 'projects_count',
            'tasks' => 'tasks_count',
            default => throw new \InvalidArgumentException("Unknown metric: {$metric}"),
        };

        $start = Carbon::parse($startDate)->subDay();
        $end = Carbon::parse($endDate);
        $rows = OrgDailyMetric::query()
            ->where('organization_id', $orgId)
            ->whereBetween('metric_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('metric_date')
            ->get(['metric_date', $column])
            ->keyBy(fn ($r) => Carbon::parse($r->metric_date)->toDateString());

        $labels = [];
        $values = [];
        $diffInDays = Carbon::parse($startDate)->diffInDays($endDate);

        for ($i = 0; $i <= $diffInDays; $i++) {
            $day = Carbon::parse($startDate)->addDays($i);
            $dayKey = $day->toDateString();
            $prevKey = $day->copy()->subDay()->toDateString();

            $curr = (int) (($rows[$dayKey] ?? null)?->{$column} ?? 0);
            $prev = (int) (($rows[$prevKey] ?? null)?->{$column} ?? 0);

            $labels[] = $day->format('M d');
            $values[] = max(0, $curr - $prev);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Monthly revenue for the last 6 months (paid invoices).
     * Single grouped query instead of 6 separate sum queries.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    private function monthlyRevenueSeries(?int $orgId): array
    {
        $start = now()->subMonths(5)->startOfMonth();
        $end = now()->endOfMonth();

        $rows = Invoice::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->whereRaw('lower(status) = ?', ['paid'])
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw("date_trunc('month', paid_at)::date as month")
            ->selectRaw('COALESCE(SUM(total_cents), 0) as total')
            ->groupByRaw("date_trunc('month', paid_at)")
            ->orderByRaw("date_trunc('month', paid_at)")
            ->get();

        $byMonth = $rows->keyBy(fn ($r) => Carbon::parse($r->month)->format('Y-m'));

        $labels = [];
        $values = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M');
            $key = $date->format('Y-m');
            $values[] = (int) ($byMonth[$key]->total ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Return a series of counts by day within a custom date range.
     *
     * @param  string  $table  The database table name (e.g., 'clients').
     * @param  ?int  $orgId  The active organization ID.
     * @param  string  $startDate  The raw start date (YYYY-MM-DD).
     * @param  string  $endDate  The raw end date (YYYY-MM-DD).
     * @return array{labels: list<string>, values: list<int>} An array containing labels (M d) and values (counts).
     */
    private function seriesByDay(string $table, ?int $orgId, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $diffInDays = $start->copy()->diffInDays($end);

        // 1. Fetch the actual counts from the database for the given range
        $rows = DB::table($table)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('d')
            ->orderBy('d')
            // Pluck returns an associative array where key is 'd' (date string) and value is 'c' (count)
            ->pluck('c', 'd');

        $labels = [];
        $values = [];

        // 2. Iterate over the entire date range (from start to end)
        for ($i = 0; $i <= $diffInDays; $i++) {
            $day = $start->copy()->addDays($i);
            $dayKey = $day->toDateString(); // YYYY-MM-DD used as the lookup key

            $labels[] = $day->format('M d'); // Display format for the chart label
            $values[] = $rows[$dayKey] ?? 0;  // Use the fetched count, or 0 if no records exist for that day
        }

        // Return a shape compatible with MiniArea (labels: string[], values: number[])
        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }
}
