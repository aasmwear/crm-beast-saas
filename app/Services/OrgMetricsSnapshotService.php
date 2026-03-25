<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrgDailyMetric;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Snapshot daily org-level metrics into org_daily_metrics.
 *
 * Designed for idempotent upsert: safe to re-run for any date.
 * Queries use raw DB counts so soft-deleted rows are excluded
 * for models that use SoftDeletes (clients, tasks, attendance).
 */
final class OrgMetricsSnapshotService
{
    /**
     * Snapshot metrics for a single org on a given date.
     *
     * Counts reflect the cumulative state as of end-of-day for the metric date,
     * except activities_count which counts activity created ON that date.
     */
    public function snapshotOrg(Organization $org, CarbonImmutable $date): OrgDailyMetric
    {
        $orgId = $org->id;
        $endOfDay = $date->endOfDay();
        $startOfDay = $date->startOfDay();
        $dateStr = $date->toDateString();

        $clientsCount = DB::table('clients')
            ->where('organization_id', $orgId)
            ->whereNull('deleted_at')
            ->where('created_at', '<=', $endOfDay)
            ->count();

        $projectsCount = DB::table('projects')
            ->where('organization_id', $orgId)
            ->where('created_at', '<=', $endOfDay)
            ->count();

        $tasksCount = DB::table('tasks')
            ->where('organization_id', $orgId)
            ->whereNull('deleted_at')
            ->where('created_at', '<=', $endOfDay)
            ->count();

        $openTasksCount = DB::table('tasks')
            ->where('organization_id', $orgId)
            ->whereNull('deleted_at')
            ->where('created_at', '<=', $endOfDay)
            ->whereRaw("lower(trim(coalesce(status,''))) NOT IN ('completed','done','approved','closed')")
            ->count();

        $attendanceCount = DB::table('attendance')
            ->where('organization_id', $orgId)
            ->whereNull('deleted_at')
            ->where('created_at', '<=', $endOfDay)
            ->count();

        $activitiesCount = DB::table('activities')
            ->where('organization_id', $orgId)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->count();

        $invoicesCount = DB::table('invoices')
            ->where('organization_id', $orgId)
            ->where('created_at', '<=', $endOfDay)
            ->count();

        $revenueCents = (int) DB::table('invoices')
            ->where('organization_id', $orgId)
            ->whereRaw('lower(status) = ?', ['paid'])
            ->where('created_at', '<=', $endOfDay)
            ->sum('total_cents');

        $outstandingCents = (int) DB::table('invoices')
            ->where('organization_id', $orgId)
            ->whereRaw("lower(trim(status)) IN ('sent','overdue')")
            ->where('created_at', '<=', $endOfDay)
            ->sum('total_cents');

        $usersCount = DB::table('organization_user')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $orgId)
            ->whereNull('users.client_id')
            ->count();

        return OrgDailyMetric::query()->updateOrCreate(
            [
                'organization_id' => $orgId,
                'metric_date' => $dateStr,
            ],
            [
                'clients_count' => $clientsCount,
                'projects_count' => $projectsCount,
                'tasks_count' => $tasksCount,
                'open_tasks_count' => $openTasksCount,
                'attendance_count' => $attendanceCount,
                'activities_count' => $activitiesCount,
                'invoices_count' => $invoicesCount,
                'revenue_cents' => $revenueCents,
                'outstanding_cents' => $outstandingCents,
                'users_count' => $usersCount,
            ],
        );
    }

    /**
     * Snapshot metrics for ALL orgs on a given date.
     *
     * @return int Number of orgs snapshotted
     */
    public function snapshotAllOrgs(CarbonImmutable $date): int
    {
        $count = 0;

        Organization::query()
            ->select('id')
            ->orderBy('id')
            ->chunk(100, function ($orgs) use ($date, &$count): void {
                foreach ($orgs as $org) {
                    $this->snapshotOrg($org, $date);
                    $count++;
                }
            });

        return $count;
    }
}
