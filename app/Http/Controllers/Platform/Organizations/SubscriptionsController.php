<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Billing\EntitlementsService;
use App\Services\Billing\SeatCounter;
use App\Support\PlanCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only platform admin org subscriptions overview.
 */
final class SubscriptionsController extends Controller
{
    public function __construct(
        private EntitlementsService $entitlements,
        private SeatCounter $seatCounter,
    ) {
    }

    public function index(Request $request): Response
    {
        $query = Organization::query()
            ->with(['billingSubscription', 'addons' => fn ($q) => $q->active()])
            ->orderBy('name');

        if ($search = $request->filled('search') ? (string) $request->input('search') : null) {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)
                    ->orWhere('slug', 'ilike', $term);
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if ($status === 'none') {
                $query->whereDoesntHave('billingSubscription');
            } else {
                $query->whereHas('billingSubscription', fn ($q) => $q->where('status', $status));
            }
        }

        if ($request->filled('plan')) {
            $plan = (string) $request->input('plan');
            if ($plan !== '' && PlanCatalog::isValidPlanKey($plan)) {
                $query->where(function ($q) use ($plan) {
                    $q->whereHas('billingSubscription', fn ($sb) => $sb->where('plan_key', $plan))
                        ->orWhere(function ($q2) use ($plan) {
                            $q2->whereDoesntHave('billingSubscription')->where('plan', $plan);
                        });
                });
            }
        }

        $paginator = $query->paginate(perPage: min((int) $request->input('per_page', 15), 50))
            ->withQueryString();

        $organizations = $paginator->getCollection()->map(fn (Organization $org) => $this->mapOrgToBillingOverview($org));

        $paginator->setCollection($organizations);

        return Inertia::render('Platform/Organizations/SubscriptionsIndex', [
            'organizations' => $paginator,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'plan' => $request->input('plan'),
            ],
            'planKeys' => PlanCatalog::planKeys(),
            'statusOptions' => [
                'none',
                'active',
                'trialing',
                'past_due',
                'incomplete',
                'canceled',
                'unpaid',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOrgToBillingOverview(Organization $org): array
    {
        $sub = $org->billingSubscription;
        $planKey = $sub?->plan_key ?? null;
        if ($planKey === null || $planKey === '' || ! PlanCatalog::isValidPlanKey($planKey)) {
            $legacyPlan = $org->plan ?? null;
            $planKey = ($legacyPlan !== null && $legacyPlan !== '' && PlanCatalog::isValidPlanKey((string) $legacyPlan))
                ? (string) $legacyPlan
                : PlanCatalog::defaultPlanKey();
        } else {
            $planKey = (string) $planKey;
        }

        $planDefaults = PlanCatalog::get($planKey);
        $seatsIncluded = $sub?->seats_included ?? $planDefaults['seats_included'] ?? 5;
        $seatLimit = $sub?->seat_limit;
        $activeSeats = $this->seatCounter->countActiveSeats($org);

        $entitlements = $this->entitlements->forOrg($org);
        $keyEntitlements = [
            'api_rpm' => $entitlements['api_rpm'] ?? null,
            'storage_gb' => $entitlements['storage_gb'] ?? null,
            'exports_per_day' => $entitlements['exports_per_day'] ?? null,
        ];

        $addonsSummary = $org->addons
            ->map(fn ($a) => [
                'addon_key' => $a->addon_key,
                'mode' => $a->mode ?? 'augment',
                'value_int' => $a->value_int,
                'quantity' => $a->quantity,
            ])
            ->values()
            ->all();

        return [
            'id' => $org->id,
            'name' => $org->name,
            'slug' => $org->slug,
            'has_stripe_id' => $org->hasStripeId(),
            'plan_key' => $planKey,
            'status' => $sub?->status ?? 'none',
            'seats_included' => $seatsIncluded,
            'seat_limit' => $seatLimit,
            'active_seats' => $activeSeats,
            'addons_summary' => $addonsSummary,
            'entitlements' => $keyEntitlements,
            'trial_ends_at' => $sub?->trial_ends_at?->toIso8601String(),
            'current_period_ends_at' => $sub?->current_period_ends_at?->toIso8601String(),
        ];
    }
}
