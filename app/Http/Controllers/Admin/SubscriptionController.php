<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class SubscriptionController extends Controller
{
    /**
     * Show current plan status and Subscribe / Manage Billing actions.
     */
    public function index(Organization $organization): InertiaResponse
    {
        $subscription = $organization->subscription('default');
        $onPro = $subscription && $subscription->active();
        $onTrial = $organization->onGenericTrial() || ($subscription && $subscription->onTrial());

        $currentPlan = $onPro ? 'Pro Plan' : ($onTrial ? 'Free Trial' : 'Free');
        $nextPayment = null;
        if ($subscription && $subscription->active()) {
            $nextPayment = $subscription->currentPeriodEnd()?->format('Y-m-d');
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

        $plans = [
            [
                'id' => 'pro',
                'name' => 'Pro',
                'price' => 79,
                'interval' => 'month',
                'features' => [
                    'Up to 25 team members',
                    'Unlimited projects',
                    '10 GB storage',
                    'Priority support',
                    'Advanced analytics',
                    'API access',
                ],
                'recommended' => true,
            ],
        ];

        return Inertia::render('Billing/Index', [
            'organization' => $organization->only(['id', 'slug', 'name']),
            'currentPlan' => $currentPlan,
            'nextPayment' => $nextPayment,
            'trialEndsAt' => $trialEndsAt,
            'invoices' => $invoices,
            'plans' => $plans,
            'hasActiveSubscription' => $onPro,
            'stripeConfigured' => ! empty(config('cashier.secret')),
        ]);
    }

    /**
     * Create a Checkout Session for Pro Monthly and return the URL.
     */
    public function checkout(Request $request, Organization $organization): JsonResponse
    {
        $priceId = config('services.stripe.price_pro_monthly');
        if (empty($priceId)) {
            return response()->json(['error' => 'Pro plan price not configured. Set STRIPE_PRICE_PRO_MONTHLY in .env.'], 500);
        }

        if (empty(config('cashier.secret'))) {
            return response()->json(['error' => 'Stripe is not configured.'], 500);
        }

        if ($organization->subscription('default')?->active()) {
            return response()->json(['error' => 'Already subscribed to Pro.'], 400);
        }

        $successUrl = route('billing.index', ['organization' => $organization->slug]).'?checkout=success';
        $cancelUrl = route('billing.index', ['organization' => $organization->slug]).'?checkout=cancelled';

        $checkout = $organization->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

        return response()->json(['url' => $checkout->url]);
    }

    /**
     * Redirect to the Stripe Customer Portal for managing subscription/cards.
     */
    public function portal(Request $request, Organization $organization): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if (empty(config('cashier.secret'))) {
            return back()->with('error', 'Stripe is not configured.');
        }

        $returnUrl = route('billing.index', ['organization' => $organization->slug]);

        return $organization->redirectToBillingPortal($returnUrl);
    }
}
