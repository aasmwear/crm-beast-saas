<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the main dashboard, filtered by the request's date range.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The Inertia response.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $orgId = $user?->active_organization_id;

        // Resolve organization details
        $org = DB::table('organizations')->select('id', 'name', 'slug')
            ->when($orgId, fn ($q) => $q->where('id', $orgId))
            ->when(! $orgId, fn ($q) => $q->orderBy('id'))
            ->first();

        // 1. Get dates from query parameters or set defaults (30 days ago to today)
        // Default: 30 days including today (now()->subDays(29))
        $defaultStart = now()->subDays(29)->toDateString();
        $defaultEnd = now()->toDateString();

        // These are the RAW dates (YYYY-MM-DD) used for DB queries and Flatpickr initialization
        $startDate = $request->query('start_date', $defaultStart);
        $endDate = $request->query('end_date', $defaultEnd);

        // Prepare dates for database query (inclusive range)
        $dbStartDate = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
        $dbEndDate = Carbon::parse($endDate)->endOfDay()->toDateTimeString();

        // 2. Use the date range to filter your counts (KPIs)
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
            'tasks_open' => Task::query()
                ->whereBetween('created_at', [$dbStartDate, $dbEndDate])
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('status', 'open') // Assuming a status column
                ->count(),
            'tasks_completed' => Task::query()
                ->whereBetween('created_at', [$dbStartDate, $dbEndDate])
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('status', 'completed') // Assuming a status column
                ->count(),
        ];

        // 3. Generate Time-Series Chart Data for the MiniArea components
        $clients30d = $this->seriesByDay('clients', $orgId, $startDate, $endDate);
        $projects30d = $this->seriesByDay('projects', $orgId, $startDate, $endDate);
        $tasks30d = $this->seriesByDay('tasks', $orgId, $startDate, $endDate);

        // 4. Generate Workload Data for the Donut Chart (e.g., tasks grouped by status)
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

        // Mock data for other dashboard components
        $tasksByAssignee = [
            'labels' => ['Alice', 'Bob', 'Charlie'],
            'values' => [15, 8, 22],
        ];

        $recentActivity = [
            ['id' => 1, 'when' => '1h ago', 'message' => 'New client "TechCorp" added.'],
            ['id' => 2, 'when' => '3h ago', 'message' => 'Task "Deploy feature X" was completed.'],
            ['id' => 3, 'when' => '1d ago', 'message' => 'Project "Migration 2025" moved to "Review".'],
        ];

        $deadlines = [
            ['id' => 1, 'title' => 'Project Alpha Final Review', 'due' => 'Oct 30'],
            ['id' => 2, 'title' => 'Client Y Invoicing', 'due' => 'Nov 01'],
        ];

        // 5. Pass all data to the Inertia view
        return Inertia::render('Dashboard/Index', [
            'org' => $org,
            'stats' => $stats,
            'charts' => [
                'clients30d' => $clients30d,
                'projects30d' => $projects30d,
                'tasks30d' => $tasks30d,
                'workload' => $workload,
                'tasksByAssignee' => $tasksByAssignee,
            ],
            'recent' => $recentActivity,
            'deadlines' => $deadlines,

            // Pass RAW dates (for Flatpickr initialization) and FORMATTED dates (for display)
            'rawStartDate' => $startDate,
            'rawEndDate' => $endDate,
            'startDateFormatted' => Carbon::parse($startDate)->format('M d'),
            'endDateFormatted' => Carbon::parse($endDate)->format('M d, Y'),
        ]);
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
