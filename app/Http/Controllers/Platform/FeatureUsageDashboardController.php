<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
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

    public function index(Request $request): Response
    {
        $totalOrgs = Organization::count();

        $adoption = $this->loadAdoptionMetrics();
        $billingSetup = $this->loadBillingSetupMetrics();
        $orgsUsingAny = $this->countOrgsUsingAnyModule();
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
