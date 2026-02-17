<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

final class PaymentController extends Controller
{
    /**
     * Create a Stripe Checkout Session and redirect the client to pay.
     */
    public function pay(Request $request, Invoice $invoice): RedirectResponse|Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (empty($user->client_id) || (int) $invoice->client_id !== (int) $user->client_id) {
            abort(404);
        }

        if (strtolower((string) $invoice->status) === 'paid') {
            return redirect()->route('portal.dashboard')->with('error', 'This invoice is already paid.');
        }

        $secret = Config::get('services.stripe.secret');
        if (empty($secret)) {
            return redirect()->route('portal.dashboard')->with('error', 'Payment is not configured.');
        }

        Stripe::setApiKey($secret);

        $successUrl = route('portal.invoices.success', ['invoice' => $invoice->id]);
        $cancelUrl = route('portal.dashboard');

        try {
            $session = StripeSession::create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($invoice->currency),
                            'product_data' => [
                                'name' => 'Invoice #' . $invoice->number,
                                'description' => 'Payment for invoice ' . $invoice->number,
                            ],
                            'unit_amount' => $invoice->total_cents,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'invoice_id' => (string) $invoice->id,
                    'organization_id' => (string) $invoice->organization_id,
                ],
            ]);
        } catch (ApiErrorException $e) {
            report($e);

            return redirect()->route('portal.dashboard')->with('error', 'Unable to create payment session. Please try again.');
        }

        return redirect()->away($session->url);
    }

    /**
     * Success page after Stripe Checkout (user is redirected here by Stripe).
     */
    public function success(Request $request, Invoice $invoice): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (empty($user->client_id) || (int) $invoice->client_id !== (int) $user->client_id) {
            abort(404);
        }

        return Inertia::render('Portal/InvoiceSuccess', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status,
                'paid_at' => $invoice->paid_at?->toIso8601String(),
            ],
        ]);
    }
}
