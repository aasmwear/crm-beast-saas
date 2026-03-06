<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\OrganizationSubscription;
use App\Services\AuditLogger;
use App\Services\Billing\EntitlementsService;
use App\Services\Billing\SeatCounter;
use App\Services\Billing\StripeSubscriptionService;
use App\Support\FeatureCatalog;
use App\Support\PlanCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class SubscriptionController extends Controller
{
    /**
     * Show current plan status and Subscribe / Manage Billing actions.
     */
    public function index(Organization $organization): InertiaResponse
    {
        abort_unless(request()->user()?->can('billing.view'), 403);

        $cashierSubscription = $organization->subscription('default');
        $onPro = $cashierSubscription && $cashierSubscription->active();
        $onTrial = $organization->onGenericTrial() || ($cashierSubscription && $cashierSubscription->onTrial());

        $currentPlan = $onPro ? 'Pro Plan' : ($onTrial ? 'Free Trial' : 'Free');
        $nextPayment = null;
        if ($cashierSubscription && $cashierSubscription->active()) {
            $nextPayment = $cashierSubscription->currentPeriodEnd()?->format('Y-m-d');
        }
        $trialEndsAt = $organization->trial_ends_at?->format('Y-m-d');

        $invoices = [];
        if ($organization->hasStripeId()) {
            try {
                $invoices = $organization->invoices()->map(fn ($inv) => [
                    'id' => $inv->id,
                    'date' => $inv->date()?->format('Y-m-d'),
                    'invoice_number' => $inv->number ?? $inv->id,
                    'amount' => $inv->rawTotal() / 100,
                    'status' => $inv->isPaid() ? 'Paid' : 'Pending',
                    'pdf_url' => $inv->invoice_pdf ?? '#',
                ])->toArray();
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch invoices for org', ['org' => $organization->id, 'error' => $e->getMessage()]);
            }
        }

        $stripePriceMap = collect((array) config('billing.stripe_prices', []))
            ->map(fn ($value) => is_string($value) ? trim($value) : null)
            ->toArray();

        $plans = [
            [
                'id' => PlanCatalog::PLAN_STARTER,
                'name' => 'Starter',
                'price' => 0,
                'interval' => 'month',
                'features' => [
                    'Up to 5 team members',
                    'Core CRM features',
                    '5 GB storage',
                    'Basic API limits',
                ],
                'recommended' => false,
                'has_stripe_price' => ! empty($stripePriceMap[PlanCatalog::PLAN_STARTER] ?? null),
            ],
            [
                'id' => PlanCatalog::PLAN_PRO,
                'name' => 'Pro',
                'price' => 79,
                'interval' => 'month',
                'features' => [
                    'Up to 25 team members',
                    'Unlimited projects',
                    '50 GB storage',
                    'Priority support',
                    'Advanced analytics',
                    'API access',
                ],
                'recommended' => true,
                'has_stripe_price' => ! empty($stripePriceMap[PlanCatalog::PLAN_PRO] ?? null),
            ],
            [
                'id' => PlanCatalog::PLAN_ENTERPRISE,
                'name' => 'Enterprise',
                'price' => 0,
                'interval' => 'custom',
                'features' => [
                    'High seat limits',
                    'Custom capacity planning',
                    'Priority onboarding',
                    'Advanced integrations',
                ],
                'recommended' => false,
                'has_stripe_price' => ! empty($stripePriceMap[PlanCatalog::PLAN_ENTERPRISE] ?? null),
            ],
        ];

        // Canonical billing data from organization_subscriptions / fallbacks
        $sub = $organization->billingSubscription;
        $planKey = $sub?->plan_key;
        if ($planKey === null || $planKey === '') {
            $planKey = $organization->plan ?? null;
            if ($planKey === null || $planKey === '' || ! PlanCatalog::isValidPlanKey((string) $planKey)) {
                $planKey = PlanCatalog::defaultPlanKey();
            } else {
                $planKey = (string) $planKey;
            }
        }
        $planDefaults = PlanCatalog::get($planKey);

        $subscription = [
            'plan_key' => $planKey,
            'status' => $sub?->status ?? 'none',
            'trial_ends_at' => $sub?->trial_ends_at?->toIso8601String() ?? $organization->trial_ends_at?->toIso8601String(),
            'current_period_ends_at' => $sub?->current_period_ends_at?->toIso8601String(),
            'seats_included' => $sub?->seats_included ?? ($planDefaults['seats_included'] ?? 5),
            'seat_limit' => $sub?->seat_limit,
        ];

        $seatCounter = app(SeatCounter::class);
        $seats = [
            'active_count' => $seatCounter->countActiveSeats($organization),
            'can_add_seat' => $seatCounter->canAddSeat($organization),
        ];

        $entitlementsService = app(EntitlementsService::class);
        $entitlements = $entitlementsService->forOrg($organization);

        $addons = $organization->addons()
            ->orderBy('addon_key')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'addon_key' => $a->addon_key,
                'mode' => $a->mode ?? 'augment',
                'quantity' => $a->quantity,
                'value_int' => $a->value_int,
                'active' => $a->active,
                'starts_at' => $a->starts_at?->toIso8601String(),
                'ends_at' => $a->ends_at?->toIso8601String(),
            ])
            ->values()
            ->toArray();

        return Inertia::render('Billing/Index', [
            'organization' => $organization->only(['id', 'slug', 'name']),
            'currentPlan' => $currentPlan,
            'nextPayment' => $nextPayment,
            'trialEndsAt' => $trialEndsAt,
            'invoices' => $invoices,
            'plans' => $plans,
            'hasActiveSubscription' => $onPro,
            'stripeConfigured' => ! empty(config('cashier.secret')),
            'stripeEnabled' => ! empty(config('cashier.secret')) && ! empty(config('services.stripe.key')),
            'subscription' => $subscription,
            'seats' => $seats,
            'entitlements' => $entitlements,
            'addons' => $addons,
            'canUpdateBilling' => request()->user()?->can('billing.update') ?? false,
            'canManageBilling' => request()->user()?->can('billing.manage') ?? false,
            'planKeys' => PlanCatalog::planKeys(),
            'addonKeys' => FeatureCatalog::addonNumericKeys(),
        ]);
    }

    /**
     * Update the canonical plan_key for the org subscription (internal control-plane).
     */
    public function updatePlan(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->can('billing.update'), 403);

        $validated = $request->validate([
            'plan_key' => ['required', 'string', Rule::in(PlanCatalog::planKeys())],
        ]);

        $sub = $organization->billingSubscription;
        $planDefaults = PlanCatalog::get($validated['plan_key']);
        $seatsIncluded = $planDefaults['seats_included'] ?? 5;

        if ($sub === null) {
            $sub = OrganizationSubscription::create([
                'organization_id' => $organization->id,
                'plan_key' => $validated['plan_key'],
                'status' => OrganizationSubscription::STATUS_ACTIVE,
                'seats_included' => $seatsIncluded,
            ]);
            AuditLogger::log(
                $organization,
                $request->user(),
                'created',
                'subscription',
                $sub->id,
                ['plan_key' => $validated['plan_key'], 'seats_included' => $seatsIncluded],
            );
        } else {
            $oldKey = $sub->plan_key;
            $sub->update([
                'plan_key' => $validated['plan_key'],
                'seats_included' => $seatsIncluded,
            ]);
            AuditLogger::log(
                $organization,
                $request->user(),
                'plan_changed',
                'subscription',
                $sub->id,
                ['from' => $oldKey, 'to' => $validated['plan_key']],
            );
        }

        app(EntitlementsService::class)->clearCache($organization);

        return redirect()->route('billing.index', ['organization' => $organization->slug])
            ->with('success', 'Plan updated.');
    }

    /**
     * Create an organization add-on (internal control-plane).
     */
    public function storeAddon(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()?->can('billing.update'), 403);

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
            $request->user(),
            'created',
            'addon',
            $addon->id,
            ['addon_key' => $addon->addon_key, 'mode' => $addon->mode, 'value_int' => $addon->value_int],
        );

        app(EntitlementsService::class)->clearCache($organization);

        return redirect()->route('billing.index', ['organization' => $organization->slug])
            ->with('success', 'Add-on created.');
    }

    /**
     * Update an organization add-on (internal control-plane).
     */
    public function updateAddon(Request $request, Organization $organization, OrganizationAddon $addon): RedirectResponse
    {
        abort_unless($request->user()?->can('billing.update'), 403);

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
            AuditLogger::log(
                $organization,
                $request->user(),
                'updated',
                'addon',
                $addon->id,
                array_merge(['addon_key' => $addon->addon_key], $changes),
            );
        }

        app(EntitlementsService::class)->clearCache($organization);

        return redirect()->route('billing.index', ['organization' => $organization->slug])
            ->with('success', 'Add-on updated.');
    }

    /**
     * Deactivate an add-on (sets active=false). Hard delete not used.
     */
    public function destroyAddon(Request $request, Organization $organization, OrganizationAddon $addon): RedirectResponse
    {
        abort_unless($request->user()?->can('billing.update'), 403);

        if (! $addon->active) {
            return redirect()->route('billing.index', ['organization' => $organization->slug])
                ->with('info', 'Add-on already deactivated.');
        }

        $addon->active = false;
        $addon->save();

        AuditLogger::log(
            $organization,
            $request->user(),
            'deactivated',
            'addon',
            $addon->id,
            ['addon_key' => $addon->addon_key],
        );

        app(EntitlementsService::class)->clearCache($organization);

        return redirect()->route('billing.index', ['organization' => $organization->slug])
            ->with('success', 'Add-on deactivated.');
    }

    /**
     * Start/switch a Stripe subscription for a selected internal plan_key.
     */
    public function checkout(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($request->user()?->can('billing.manage'), 403);

        $validated = $request->validate([
            'plan_key' => ['required', 'string', Rule::in(PlanCatalog::planKeys())],
        ]);

        $planKey = (string) $validated['plan_key'];

        if (empty(config('cashier.secret')) || empty(config('services.stripe.key'))) {
            return response()->json(['error' => 'Stripe is not configured for this environment.'], 422);
        }

        $priceId = (string) (config("billing.stripe_prices.{$planKey}") ?? '');
        if ($priceId === '') {
            return response()->json([
                'error' => 'This plan is not available for Stripe self-serve subscription yet.',
            ], 422);
        }

        $planDefaults = PlanCatalog::get($planKey);
        $seatsIncluded = $planDefaults['seats_included'] ?? 5;

        $successUrl = route('billing.index', ['organization' => $organization->slug]).'?checkout=success';
        $cancelUrl = route('billing.index', ['organization' => $organization->slug]).'?checkout=cancelled';

        /** @var StripeSubscriptionService $stripeBilling */
        $stripeBilling = app(StripeSubscriptionService::class);

        try {
            $result = $stripeBilling->startForPlan($organization, $priceId, [
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ], $planKey);
        } catch (\Throwable $e) {
            Log::warning('Stripe subscription initiation failed', [
                'organization_id' => $organization->id,
                'plan_key' => $planKey,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unable to start subscription right now. Please try again.',
            ], 422);
        }

        $status = match ($result['mode']) {
            'checkout' => 'incomplete',
            default => (string) ($result['status'] ?? OrganizationSubscription::STATUS_ACTIVE),
        };

        $sub = OrganizationSubscription::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'plan_key' => $planKey,
                'status' => $status,
                'seats_included' => $seatsIncluded,
                'trial_ends_at' => $result['trial_ends_at'] ?? null,
                'current_period_ends_at' => $result['current_period_ends_at'] ?? null,
            ]
        );

        AuditLogger::log(
            $organization,
            $request->user(),
            'subscription_initiated',
            'subscription',
            $sub->id,
            [
                'plan_key' => $planKey,
                'mode' => $result['mode'],
                'status' => $status,
            ],
        );

        app(EntitlementsService::class)->clearCache($organization);

        if (($result['mode'] ?? null) === 'checkout' && ! empty($result['checkout_url'])) {
            return response()->json([
                'url' => $result['checkout_url'],
                'message' => 'Redirecting to Stripe Checkout...',
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Subscription updated successfully.',
        ]);
    }

    /**
     * Redirect to the Stripe Customer Portal for managing subscription/cards.
     */
    public function portal(Request $request, Organization $organization): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()?->can('billing.view'), 403);

        if (empty(config('cashier.secret'))) {
            return back()->with('error', 'Stripe is not configured.');
        }

        $returnUrl = route('billing.index', ['organization' => $organization->slug]);

        return $organization->redirectToBillingPortal($returnUrl);
    }
}
