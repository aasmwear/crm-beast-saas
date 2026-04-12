<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\OrganizationSubscription;
use App\Models\StripeWebhookEvent;
use App\Services\AuditLogger;
use App\Services\Billing\EntitlementsService;
use App\Services\Billing\SeatCounter;
use App\Support\FeatureCatalog;
use App\Support\PlanCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform admin org subscriptions overview and manual billing overrides.
 * Internal control-plane: does NOT mutate Stripe.
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
            ->with(['billingSubscription', 'addons'])
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

        $orgIds = $paginator->getCollection()->pluck('id')->all();
        $webhookStats = $this->loadWebhookSupportStats($orgIds);

        $organizations = $paginator->getCollection()->map(function (Organization $org) use ($webhookStats) {
            return $this->mapOrgToBillingOverview($org, $webhookStats[$org->id] ?? []);
        });

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
            'addonKeys' => FeatureCatalog::addonNumericKeys(),
        ]);
    }

    /**
     * Load webhook support indicators for the given org IDs (batched, avoids N+1).
     *
     * @param  array<int>  $orgIds
     * @return array<int, array{last_type: string|null, last_processed_at: string|null, last_status: string|null, recent_failed_count: int}>
     */
    private function loadWebhookSupportStats(array $orgIds): array
    {
        if (count($orgIds) === 0) {
            return [];
        }

        $recentFailedCutoff = now()->subDays(7);

        $lastPerOrg = StripeWebhookEvent::query()
            ->forOrganizations($orgIds)
            ->whereNotNull('organization_id')
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->get(['organization_id', 'type', 'status', 'processed_at'])
            ->groupBy('organization_id')
            ->map(fn ($events) => $events->first())
            ->all();

        $failedCounts = StripeWebhookEvent::query()
            ->forOrganizations($orgIds)
            ->whereNotNull('organization_id')
            ->where('status', 'failed')
            ->where('created_at', '>=', $recentFailedCutoff)
            ->groupBy('organization_id')
            ->selectRaw('organization_id, count(*) as cnt')
            ->pluck('cnt', 'organization_id')
            ->all();

        $result = [];
        foreach ($orgIds as $orgId) {
            $last = $lastPerOrg[$orgId] ?? null;
            $result[$orgId] = [
                'last_type' => $last?->type,
                'last_processed_at' => $last?->processed_at?->toIso8601String(),
                'last_status' => $last?->status,
                'recent_failed_count' => (int) ($failedCounts[$orgId] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @param  array{last_type?: string|null, last_processed_at?: string|null, last_status?: string|null, recent_failed_count?: int}  $webhookStats
     * @return array<string, mixed>
     */
    private function mapOrgToBillingOverview(Organization $org, array $webhookStats = []): array
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
                'id' => $a->id,
                'addon_key' => $a->addon_key,
                'mode' => $a->mode ?? 'augment',
                'value_int' => $a->value_int,
                'quantity' => $a->quantity,
                'active' => $a->active,
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
            'webhook' => [
                'last_type' => $webhookStats['last_type'] ?? null,
                'last_processed_at' => $webhookStats['last_processed_at'] ?? null,
                'last_status' => $webhookStats['last_status'] ?? null,
                'recent_failed_count' => $webhookStats['recent_failed_count'] ?? 0,
            ],
            'tenant_billing_url' => url("/org/{$org->slug}/billing"),
        ];
    }

    public function updateSubscription(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'plan_key' => ['required', 'string', Rule::in(PlanCatalog::planKeys())],
            'status' => ['nullable', 'string', Rule::in([
                OrganizationSubscription::STATUS_ACTIVE,
                OrganizationSubscription::STATUS_TRIALING,
                OrganizationSubscription::STATUS_PAST_DUE,
                OrganizationSubscription::STATUS_INCOMPLETE,
                OrganizationSubscription::STATUS_CANCELED,
                OrganizationSubscription::STATUS_UNPAID,
            ])],
            'seat_limit' => ['nullable', 'integer', 'min:1'],
            'clear_seat_limit' => ['boolean'],
        ]);

        $planKey = (string) $validated['plan_key'];
        $planDefaults = PlanCatalog::get($planKey);
        $seatsIncluded = $planDefaults['seats_included'] ?? 5;

        $sub = $organization->billingSubscription;
        $seatLimit = ! empty($validated['clear_seat_limit'])
            ? null
            : (isset($validated['seat_limit']) ? (int) $validated['seat_limit'] : $sub?->seat_limit);

        $sub = OrganizationSubscription::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'plan_key' => $planKey,
                'status' => $validated['status'] ?? ($sub?->status ?? OrganizationSubscription::STATUS_ACTIVE),
                'seats_included' => $seatsIncluded,
                'seat_limit' => $seatLimit,
            ]
        );

        $changes = ['plan_key' => $planKey];
        if (isset($validated['seat_limit']) || ! empty($validated['clear_seat_limit'])) {
            $changes['seat_limit'] = $seatLimit;
        }
        if (! empty($validated['status'])) {
            $changes['status'] = $validated['status'];
        }
        $changes['platform_admin_id'] = $request->user('platform')?->id;

        AuditLogger::log(
            $organization,
            null,
            'platform_subscription_override',
            'subscription',
            $sub->id,
            $changes
        );

        $this->entitlements->clearCache($organization);

        return redirect()->route('platform.organizations.subscriptions')
            ->with('success', 'Subscription override saved.');
    }

    public function storeAddon(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'addon_key' => ['required', 'string', Rule::in(FeatureCatalog::addonNumericKeys())],
            'mode' => ['required', 'string', Rule::in([OrganizationAddon::MODE_AUGMENT, OrganizationAddon::MODE_SET])],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'value_int' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);
        if (! empty($validated['starts_at']) && ! empty($validated['ends_at']) && $validated['ends_at'] < $validated['starts_at']) {
            throw ValidationException::withMessages(['ends_at' => ['End date must be after or equal to start date.']]);
        }

        $addon = OrganizationAddon::create([
            'organization_id' => $organization->id,
            'addon_key' => $validated['addon_key'],
            'mode' => $validated['mode'] ?? OrganizationAddon::MODE_AUGMENT,
            'quantity' => $validated['quantity'] ?? 1,
            'value_int' => $validated['value_int'] ?? null,
            'active' => $validated['active'] ?? true,
            'starts_at' => isset($validated['starts_at']) ? $validated['starts_at'] : null,
            'ends_at' => isset($validated['ends_at']) ? $validated['ends_at'] : null,
        ]);

        AuditLogger::log(
            $organization,
            null,
            'platform_addon_created',
            'addon',
            $addon->id,
            [
                'addon_key' => $addon->addon_key,
                'mode' => $addon->mode,
                'value_int' => $addon->value_int,
                'platform_admin_id' => $request->user('platform')?->id,
            ]
        );

        $this->entitlements->clearCache($organization);

        return redirect()->route('platform.organizations.subscriptions')
            ->with('success', 'Add-on created.');
    }

    public function updateAddon(Request $request, Organization $organization, OrganizationAddon $addon): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'string', Rule::in([OrganizationAddon::MODE_AUGMENT, OrganizationAddon::MODE_SET])],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'value_int' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);
        if (! empty($validated['starts_at'] ?? null) && ! empty($validated['ends_at'] ?? null) && $validated['ends_at'] < $validated['starts_at']) {
            throw ValidationException::withMessages(['ends_at' => ['End date must be after or equal to start date.']]);
        }

        $changes = [];
        if (array_key_exists('mode', $validated) && $validated['mode'] !== $addon->mode) {
            $changes['mode'] = ['from' => $addon->mode, 'to' => $validated['mode']];
            $addon->mode = $validated['mode'];
        }
        if (array_key_exists('quantity', $validated) && $validated['quantity'] != $addon->quantity) {
            $changes['quantity'] = ['from' => $addon->quantity, 'to' => $validated['quantity']];
            $addon->quantity = (int) $validated['quantity'];
        }
        if (array_key_exists('value_int', $validated) && $validated['value_int'] != $addon->value_int) {
            $changes['value_int'] = ['from' => $addon->value_int, 'to' => $validated['value_int']];
            $addon->value_int = $validated['value_int'];
        }
        if (array_key_exists('active', $validated) && (bool) $validated['active'] !== $addon->active) {
            $changes['active'] = ['from' => $addon->active, 'to' => (bool) $validated['active']];
            $addon->active = (bool) $validated['active'];
        }
        if (array_key_exists('starts_at', $validated)) {
            $newVal = $validated['starts_at'] ? $validated['starts_at'] : null;
            if ($addon->starts_at?->format('Y-m-d') !== $newVal) {
                $addon->starts_at = $newVal;
                $changes['starts_at'] = $newVal;
            }
        }
        if (array_key_exists('ends_at', $validated)) {
            $newVal = $validated['ends_at'] ? $validated['ends_at'] : null;
            if ($addon->ends_at?->format('Y-m-d') !== $newVal) {
                $addon->ends_at = $newVal;
                $changes['ends_at'] = $newVal;
            }
        }

        $addon->save();

        if (count($changes) > 0) {
            $changes['platform_admin_id'] = $request->user('platform')?->id;
            AuditLogger::log(
                $organization,
                null,
                'platform_addon_updated',
                'addon',
                $addon->id,
                array_merge(['addon_key' => $addon->addon_key], $changes)
            );
        }

        $this->entitlements->clearCache($organization);

        return redirect()->route('platform.organizations.subscriptions')
            ->with('success', 'Add-on updated.');
    }

    public function destroyAddon(Request $request, Organization $organization, OrganizationAddon $addon): RedirectResponse
    {
        if (! $addon->active) {
            return redirect()->route('platform.organizations.subscriptions')
                ->with('info', 'Add-on already deactivated.');
        }

        $addon->active = false;
        $addon->save();

        AuditLogger::log(
            $organization,
            null,
            'platform_addon_deactivated',
            'addon',
            $addon->id,
            [
                'addon_key' => $addon->addon_key,
                'platform_admin_id' => $request->user('platform')?->id,
            ]
        );

        $this->entitlements->clearCache($organization);

        return redirect()->route('platform.organizations.subscriptions')
            ->with('success', 'Add-on deactivated.');
    }
}
