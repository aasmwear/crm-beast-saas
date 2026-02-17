<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

final class WebhookController extends Controller
{
    /**
     * Handle Stripe webhook events (checkout.session.completed).
     */
    public function handleStripe(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (empty($webhookSecret)) {
            Log::warning('Stripe webhook secret not configured');

            return response('Webhook secret not configured', 500);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook payload invalid', ['error' => $e->getMessage()]);

            return response('Invalid payload', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            $invoiceId = $session->metadata->invoice_id ?? null;
            if (empty($invoiceId)) {
                Log::warning('Stripe checkout.session.completed missing invoice_id in metadata');

                return response('Missing metadata', 400);
            }

            $invoice = Invoice::find($invoiceId);
            if (! $invoice) {
                Log::warning('Stripe webhook: invoice not found', ['invoice_id' => $invoiceId]);

                return response('Invoice not found', 404);
            }

            if (strtolower((string) $invoice->status) === 'paid') {
                Log::info('Stripe webhook: invoice already paid, skipping', ['invoice_id' => $invoiceId]);

                return response('OK', 200);
            }

            $paymentIntentId = $session->payment_intent ?? null;

            $invoice->update([
                'status' => 'Paid',
                'paid_at' => now(),
                'stripe_payment_intent_id' => is_string($paymentIntentId) ? $paymentIntentId : null,
            ]);

            ActivityLogger::log(
                null,
                $invoice,
                'Invoice paid via Stripe',
                ['session_id' => $session->id ?? null],
            );
        }

        return response('OK', 200);
    }
}
