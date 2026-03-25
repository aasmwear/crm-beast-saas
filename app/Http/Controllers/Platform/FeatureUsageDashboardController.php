<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\OrgDailyMetric;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only Feature Usage Dashboard for platform operators.
 *
 * Surfaces module adoption metrics derived from local DB counts.
 * No event tracking, no analytics SDK — just table counts.
 */
final class FeatureUsageDashboardController extends Controller
{
    /**
     * Module definitions for adoption tracking.
     * soft_delete: whether the table uses soft deletes (needs WHERE deleted_at IS NULL).
     */
    private const MODULES = [
        'clients' => ['table' => 'clients', 'org_col' => 'organization_id', 'soft_delete' => true, 'label' => 'Clients'],
        'projects' => ['table' => 'projects', 'org_col' => 'organization_id', 'soft_delete' => true, 'label' => 'Projects'],
        'tasks' => ['table' => 'tasks', 'org_col' => 'organization_id', 'soft_delete' => true, 'label' => 'Tasks'],
        'attendance' => ['table' => 'attendance', 'org_col' => 'organization_id', 'soft_delete' => true, 'label' => 'Attendance'],
        'invoices' => ['table' => 'invoices', 'org_col' => 'organization_id', 'soft_delete' => false, 'label' => 'Invoices'],
    ];

    /**
     * org_daily_metrics column for each MODULES key (cumulative counts as of metric_date EOD).
     *
     * @var array<string, string>
     */
    private const MODULE_SNAPSHOT_COLUMN = [
        'clients' => 'clients_count',
        'projects' => 'projects_count',
        'tasks' => 'tasks_count',
        'attendance' => 'attendance_count',
        'invoices' => 'invoices_count',
    ];

    public function index(Request $request): Response
    {
        $totalOrgs = Organization::count();

        $snapshotDate = $this->platformSnapshotReferenceDate();
        $useSnapshotAdoption = $this->hasFullPlatformSnapshotCoverage($snapshotDate);

        if ($useSnapshotAdoption) {
            $adoption = $this->loadAdoptionMetricsFromSnapshot($snapshotDate);
            $orgsUsingAny = $this->countOrgsUsingAnyModuleFromSnapshot($snapshotDate);
        } else {
            $adoption = $this->loadAdoptionMetrics();
            $orgsUsingAny = $this->countOrgsUsingAnyModule();
        }

        $billingSetup = $this->loadBillingSetupMetrics();
        $avgModules = $this->computeAvgModulesPerOrg($totalOrgs, $adoption);

        return Inertia::render('Platform/FeatureUsage/Index', [
            'total_orgs' => $totalOrgs,
            'adoption' => $adoption,
            'billing_setup' => $billingSetup,
            'summary' => [
                'total_orgs' => $totalOrgs,
                'orgs_using_any_module' => $orgsUsingAny,
                'avg_modules_per_org' => $avgModules,
                'modules_tracked' => count(self::MODULES),
            ],
        ]);
    }

    /**
     * Aligns with scheduled snapshots: "yesterday" in app timezone.
     */
    private function platformSnapshotReferenceDate(): CarbonImmutable
    {
        return CarbonImmutable::yesterday();
    }

    /**
     * Full coverage: one org_daily_metrics row per organization for the reference date.
     * Otherwise adoption aggregates would mix snapshot and missing orgs incorrectly.
     */
    private function hasFullPlatformSnapshotCoverage(CarbonImmutable $date): bool
    {
        $dateStr = $date->toDateString();
        $totalOrgs = Organization::count();
        $rowCount = OrgDailyMetric::query()
            ->where('metric_date', $dateStr)
            ->count();

        return $rowCount === $totalOrgs;
    }

    /**
     * @return array<string, array{orgs_with_any: int, total_records: int, label: string}>
     */
    private function loadAdoptionMetricsFromSnapshot(CarbonImmutable $date): array
    {
        $dateStr = $date->toDateString();
        $columns = [];
        foreach (self::MODULE_SNAPSHOT_COLUMN as $key => $col) {
            $columns[] = 'COALESCE(SUM(' . $col . '), 0) AS ' . $key . '_total';
            $columns[] = 'COALESCE(SUM(CASE WHEN ' . $col . ' > 0 THEN 1 ELSE 0 END), 0) AS ' . $key . '_orgs';
        }

        $row = DB::table('org_daily_metrics')
            ->where('metric_date', $dateStr)
            ->selectRaw(implode(', ', $columns))
            ->first();

        if ($row === null) {
            return $this->emptyAdoptionMetrics();
        }

        $result = [];
        foreach (self::MODULES as $key => $config) {
            $totalKey = $key . '_total';
            $orgsKey = $key . '_orgs';
            $result[$key] = [
                'orgs_with_any' => (int) ($row->{$orgsKey} ?? 0),
                'total_records' => (int) ($row->{$totalKey} ?? 0),
                'label' => $config['label'],
            ];
        }

        return $result;
    }

    /**
     * @return array<string, array{orgs_with_any: int, total_records: int, label: string}>
     */
    private function emptyAdoptionMetrics(): array
    {
        $result = [];
        foreach (self::MODULES as $key => $config) {
            $result[$key] = [
                'orgs_with_any' => 0,
                'total_records' => 0,
                'label' => $config['label'],
            ];
        }

        return $result;
    }

    private function countOrgsUsingAnyModuleFromSnapshot(CarbonImmutable $date): int
    {
        $dateStr = $date->toDateString();
        $conditions = [];
        foreach (self::MODULE_SNAPSHOT_COLUMN as $col) {
            $conditions[] = $col . ' > 0';
        }
        $predicate = implode(' OR ', $conditions);

        return (int) DB::table('org_daily_metrics')
            ->where('metric_date', $dateStr)
            ->whereRaw('(' . $predicate . ')')
            ->count();
    }

    /**
     * Count distinct orgs with at least one record in each module table.
     * One query per module — each is a simple grouped count.
     *
     * @return array<string, array{orgs_with_any: int, total_records: int, label: string}>
     */
    private function loadAdoptionMetrics(): array
    {
        $result = [];

        foreach (self::MODULES as $key => $config) {
            $query = DB::table($config['table']);

            if ($config['soft_delete']) {
                $query->whereNull('deleted_at');
            }

            $stats = $query
                ->selectRaw('count(distinct ' . $config['org_col'] . ') as org_count, count(*) as total')
                ->first();

            $result[$key] = [
                'orgs_with_any' => (int) ($stats->org_count ?? 0),
                'total_records' => (int) ($stats->total ?? 0),
                'label' => $config['label'],
            ];
        }

        return $result;
    }

    /**
     * Billing/subscription setup metrics.
     *
     * @return array{stripe_linked: int, has_subscription: int, has_active_subscription: int, has_active_addons: int}
     */
    private function loadBillingSetupMetrics(): array
    {
        $stripeLinked = Organization::whereNotNull('stripe_id')
            ->where('stripe_id', '!=', '')
            ->count();

        $hasSubscription = OrganizationSubscription::distinct('organization_id')
            ->count('organization_id');

        $hasActiveSubscription = OrganizationSubscription::query()
            ->whereIn('status', [
                OrganizationSubscription::STATUS_ACTIVE,
                OrganizationSubscription::STATUS_TRIALING,
            ])
            ->distinct('organization_id')
            ->count('organization_id');

        $hasActiveAddons = (int) DB::table('organization_addons')
            ->where('active', true)
            ->distinct('organization_id')
            ->count('organization_id');

        return [
            'stripe_linked' => $stripeLinked,
            'has_subscription' => $hasSubscription,
            'has_active_subscription' => $hasActiveSubscription,
            'has_active_addons' => $hasActiveAddons,
        ];
    }

    /**
     * Count orgs using at least one tracked module (UNION of all module tables).
     */
    private function countOrgsUsingAnyModule(): int
    {
        $unions = [];
        foreach (self::MODULES as $config) {
            $where = $config['soft_delete'] ? ' WHERE deleted_at IS NULL' : '';
            $unions[] = "SELECT DISTINCT {$config['org_col']} AS org_id FROM {$config['table']}{$where}";
        }

        if (count($unions) === 0) {
            return 0;
        }

        $sql = 'SELECT count(DISTINCT org_id) AS cnt FROM (' . implode(' UNION ALL ', $unions) . ') AS all_modules';

        return (int) (DB::selectOne($sql)?->cnt ?? 0);
    }

    /**
     * Average number of modules adopted per org.
     *
     * @param  array<string, array{orgs_with_any: int, total_records: int, label: string}>  $adoption
     */
    private function computeAvgModulesPerOrg(int $totalOrgs, array $adoption): float
    {
        if ($totalOrgs === 0) {
            return 0;
        }

        $totalAdoptionPoints = 0;
        foreach ($adoption as $metrics) {
            $totalAdoptionPoints += $metrics['orgs_with_any'];
        }

        return round($totalAdoptionPoints / $totalOrgs, 1);
    }
}
