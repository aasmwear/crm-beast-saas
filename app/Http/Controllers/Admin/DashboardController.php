<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // Totals
        $stats = [
            'organizations' => (int) DB::table('organizations')->count(),
            'users' => (int) DB::table('users')->count(),
            'clients' => (int) DB::table('clients')->count(),
            'projects' => (int) DB::table('projects')->count(),
            'tasks' => (int) DB::table('tasks')->count(),
        ];

        // Recent organizations
        $recentOrgs = DB::table('organizations')
            ->latest('id')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'created_at']);

        // New orgs/users last 30 days (for the sparkline)
        $orgsByDay = $this->seriesByDay('organizations', 'created_at', 30);
        $usersByDay = $this->seriesByDay('users', 'created_at', 30);

        // Donut: projects by status (Active/Paused/Pending/Completed)
        $projectStatus = $this->countsBy('projects', 'status', [
            'Active', 'Paused', 'Pending', 'Completed',
        ]);

        // Mini cards: task review/submit pipeline (optional but nice)
        $taskFlow = $this->countsBy('tasks', 'review_status', [
            'Not Submitted', 'Submitted', 'Approved', 'Rejected',
        ]);

        // “Transactions” table: latest activity (use your notifications table)
        $activity = DB::table('notifications')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'type', 'data', 'created_at']);

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentOrgs' => $recentOrgs,
            // charts
            'spark' => [
                'labels' => $orgsByDay['labels'],
                'values' => $orgsByDay['values'],
                'title' => 'New organizations (30d)',
            ],
            'sparkUsers' => [
                'labels' => $usersByDay['labels'],
                'values' => $usersByDay['values'],
                'title' => 'New users (30d)',
            ],
            'projectsDonut' => [
                'labels' => array_keys($projectStatus),
                'values' => array_values($projectStatus),
                'center' => 'Projects',
            ],
            'taskFlow' => [
                'labels' => array_keys($taskFlow),
                'values' => array_values($taskFlow),
            ],
            'activity' => $activity,
        ]);
    }

    /**
     * Return daily counts for the last N days.
     *
     * @return array{labels: array<int,string>, values: array<int,int>}
     */
    private function seriesByDay(string $table, string $column, int $days = 30): array
    {
        $start = now()->startOfDay()->subDays($days - 1);

        $raw = DB::table($table)
            ->selectRaw("to_char(date_trunc('day', {$column}), 'YYYY-MM-DD') as d, count(*) as c")
            ->where($column, '>=', $start)
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd')
            ->all(); // ['2025-10-01' => 3, ...]

        $labels = [];
        $values = [];
        for ($i = 0; $i < $days; $i++) {
            /** @var Carbon $day */
            $day = (clone $start)->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('M d');
            /** @var int $val */
            $val = (int) ($raw[$key] ?? 0);
            $values[] = $val;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Count rows grouped by a field, returned as ordered label => value map.
     *
     * @param  array<int,string>  $order
     * @return array<string,int>
     */
    private function countsBy(string $table, string $field, array $order): array
    {
        $rows = DB::table($table)
            ->select($field, DB::raw('count(*) as c'))
            ->groupBy($field)
            ->pluck('c', $field)
            ->all();

        $result = [];
        foreach ($order as $label) {
            /** @var int $val */
            $val = (int) ($rows[$label] ?? 0);
            $result[$label] = $val;
        }

        return $result;
    }
}
