<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Support\PlanCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only Revenue / MRR Dashboard for platform operators.
 *
 * MRR is estimated from canonical plan data (organization_subscriptions),
 * NOT from Stripe invoice history. Clearly labeled as "estimated" in the UI.
 */
final class RevenueDashboardController extends Controller
{
    /**
     * Internal display prices (cents/month) aligned with tenant billing UI.
     * Starter is free; Enterprise is custom-quoted (excluded from estimated MRR).
     */
    private const PLAN_PRICE_CENTS = [
        'starter' => 0,
        'pro' => 7900,
        'enterprise' => 0,
    ];

    public function index(Request $request): Response
    {
        $totalOrgs = Organization::count();
        $stripeLinkedCount = Organization::whereNotNull('stripe_id')->where('stripe_id', '!=', '')->count();

        $subscriptionCounts = $this->loadSubscriptionStatusCounts();
        $planDistribution = $this->loadPlanDistribution();
        $mrrBreakdown = $this->computeMrrBreakdown($planDistribution);
        $totalSeats = $this->loadTotalActiveSeats();

        $activeRevenueOrgs = ($subscriptionCounts['active'] ?? 0) + ($subscriptionCounts['trialing'] ?? 0);
        $atRiskOrgs = ($subscriptionCounts['past_due'] ?? 0) + ($subscriptionCounts['unpaid'] ?? 0);

        return Inertia::render('Platform/Revenue/Index', [
            'total_orgs' => $totalOrgs,
            'stripe_linked_count' => $stripeLinkedCount,
            'total_seats' => $totalSeats,
            'subscription_counts' => $subscriptionCounts,
            'active_revenue_orgs' => $activeRevenueOrgs,
            'at_risk_orgs' => $atRiskOrgs,
            'plan_distribution' => $planDistribution,
            'mrr' => $mrrBreakdown,
        ]);
    }

    /**
     * Count subscriptions grouped by status. Orgs without a subscription row are counted as 'none'.
     *
     * @return array<string, int>
     */
    private function loadSubscriptionStatusCounts(): array
    {
        $withSub = OrganizationSubscription::query()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->mapWithKeys(fn ($cnt, $status) => [(string) $status => (int) $cnt])
            ->all();

        $totalWithSub = array_sum($withSub);
        $totalOrgs = Organization::count();
        $withSub['none'] = max(0, $totalOrgs - $totalWithSub);

        $allStatuses = ['active', 'trialing', 'past_due', 'incomplete', 'unpaid', 'canceled', 'none'];
        $result = [];
        foreach ($allStatuses as $s) {
            $result[$s] = $withSub[$s] ?? 0;
        }

        return $result;
    }

    /**
     * Count orgs by effective plan_key.
     * Orgs without a subscription row fall back to organization.plan or 'starter'.
     *
     * @return array<string, int>
     */
    private function loadPlanDistribution(): array
    {
        $fromSubscription = OrganizationSubscription::query()
            ->selectRaw('plan_key, count(*) as cnt')
            ->groupBy('plan_key')
            ->pluck('cnt', 'plan_key')
            ->mapWithKeys(fn ($cnt, $pk) => [(string) $pk => (int) $cnt])
            ->all();

        $orgIdsWithSub = OrganizationSubscription::pluck('organization_id')->all();

        $legacyQuery = Organization::query();
        if (count($orgIdsWithSub) > 0) {
            $legacyQuery->whereNotIn('id', $orgIdsWithSub);
        }

        $legacyCounts = $legacyQuery
            ->selectRaw("coalesce(nullif(plan, ''), 'starter') as effective_plan, count(*) as cnt")
            ->groupBy(DB::raw("coalesce(nullif(plan, ''), 'starter')"))
            ->pluck('cnt', 'effective_plan')
            ->mapWithKeys(fn ($cnt, $pk) => [(string) $pk => (int) $cnt])
            ->all();

        foreach ($legacyCounts as $pk => $cnt) {
            $key = PlanCatalog::isValidPlanKey($pk) ? $pk : PlanCatalog::defaultPlanKey();
            $fromSubscription[$key] = ($fromSubscription[$key] ?? 0) + $cnt;
        }

        $allPlans = PlanCatalog::planKeys();
        $result = [];
        foreach ($allPlans as $pk) {
            $result[$pk] = $fromSubscription[$pk] ?? 0;
        }

        return $result;
    }

    /**
     * Compute estimated MRR from plan distribution (active + trialing only).
     *
     * @param  array<string, int>  $planDistribution
     * @return array{estimated_total_cents: int, by_plan: array<string, array{count: int, price_cents: int, subtotal_cents: int}>, enterprise_note: string|null}
     */
    private function computeMrrBreakdown(array $planDistribution): array
    {
        $activePlanCounts = OrganizationSubscription::query()
            ->whereIn('status', [
                OrganizationSubscription::STATUS_ACTIVE,
                OrganizationSubscription::STATUS_TRIALING,
            ])
            ->selectRaw('plan_key, count(*) as cnt')
            ->groupBy('plan_key')
            ->pluck('cnt', 'plan_key')
            ->mapWithKeys(fn ($cnt, $pk) => [(string) $pk => (int) $cnt])
            ->all();

        $totalCents = 0;
        $byPlan = [];
        $enterpriseCount = 0;

        foreach (PlanCatalog::planKeys() as $pk) {
            $count = $activePlanCounts[$pk] ?? 0;
            $priceCents = self::PLAN_PRICE_CENTS[$pk] ?? 0;
            $subtotal = $count * $priceCents;
            $totalCents += $subtotal;

            $byPlan[$pk] = [
                'count' => $count,
                'price_cents' => $priceCents,
                'subtotal_cents' => $subtotal,
            ];

            if ($pk === PlanCatalog::PLAN_ENTERPRISE) {
                $enterpriseCount = $count;
            }
        }

        return [
            'estimated_total_cents' => $totalCents,
            'by_plan' => $byPlan,
            'enterprise_note' => $enterpriseCount > 0
                ? "{$enterpriseCount} enterprise org(s) excluded — custom pricing"
                : null,
        ];
    }

    /**
     * Total active (non-portal) seats across all orgs.
     */
    private function loadTotalActiveSeats(): int
    {
        return (int) DB::table('organization_user')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->whereNull('users.client_id')
            ->count();
    }
}
