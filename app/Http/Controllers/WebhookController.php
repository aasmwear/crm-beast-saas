<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\StripeWebhookEvent;
use App\Services\ActivityLogger;
use App\Services\AuditLogger;
use App\Services\Billing\StripeWebhookSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

final class WebhookController extends Controller
{
    /**
     * Handle Stripe webhook events with signature verification + idempotency.
     */
    public function handleStripe(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (empty($webhookSecret)) {
            Log::error('stripe.webhook.config_missing', [
                'detail' => 'STRIPE_WEBHOOK_SECRET not configured',
            ]);

            return response('Webhook secret not configured', 422);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('stripe.webhook.signature_invalid', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 400);
        } catch (\UnexpectedValueException $e) {
            Log::warning('stripe.webhook.payload_invalid', [
                'error' => $e->getMessage(),
            ]);

            return response('Invalid payload', 400);
        }

        $eventArray = $event->toArray();
        $eventId = (string) ($eventArray['id'] ?? '');
        $eventType = (string) ($eventArray['type'] ?? '');

        if ($eventId === '' || $eventType === '') {
            return response('Invalid payload', 422);
        }

        if (StripeWebhookEvent::query()->where('stripe_event_id', $eventId)->exists()) {
            return response('OK', 200);
        }

        try {
            $eventLog = StripeWebhookEvent::query()->create([
                'stripe_event_id' => $eventId,
                'type' => $eventType,
                'status' => 'received',
                'payload_json' => $eventArray,
            ]);
        } catch (QueryException $e) {
            // Unique key race / duplicate delivery.
            return response('OK', 200);
        }

        try {
            $notes = [];

            if ($eventType === 'checkout.session.completed') {
                $legacyInvoice = $this->processLegacyInvoiceCheckout($eventArray);
                if ($legacyInvoice['handled']) {
                    $notes[] = $legacyInvoice['notes'];
                }
            }

            $syncResult = app(StripeWebhookSyncService::class)->process($eventArray);
            if ($syncResult['handled']) {
                $notes[] = $syncResult['notes'];
            }

            $eventLog->update([
                'status' => 'processed',
                'processed_at' => Carbon::now(),
                'notes' => count($notes) > 0 ? implode('; ', $notes) : 'acknowledged',
            ]);
        } catch (\Throwable $e) {
            $organizationId = $this->resolveOrganizationIdFromEvent($eventArray);

            Log::error('Stripe webhook processing failed', [
                'stripe_event_id' => $eventId,
                'event_type' => $eventType,
                'organization_id' => $organizationId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            $eventLog->update([
                'status' => 'failed',
                'notes' => $e->getMessage(),
            ]);

            return response('Unable to process event', 422);
        }

        return response('OK', 200);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{handled: bool, notes: string}
     */
    private function processLegacyInvoiceCheckout(array $event): array
    {
        $session = (array) ($event['data']['object'] ?? []);
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $invoiceId = $metadata['invoice_id'] ?? null;

        if (! is_string($invoiceId) || trim($invoiceId) === '') {
            return ['handled' => false, 'notes' => 'no legacy invoice metadata'];
        }

        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            Log::warning('Stripe webhook: invoice not found', ['invoice_id' => $invoiceId]);

            return ['handled' => false, 'notes' => 'legacy invoice not found'];
        }

        if (strtolower((string) $invoice->status) === 'paid') {
            return ['handled' => true, 'notes' => 'legacy invoice already paid'];
        }

        $paymentIntentId = $session['payment_intent'] ?? null;

        $invoice->update([
            'status' => 'Paid',
            'paid_at' => now(),
            'stripe_payment_intent_id' => is_string($paymentIntentId) ? $paymentIntentId : null,
        ]);

        ActivityLogger::log(
            null,
            $invoice,
            'Invoice paid via Stripe',
            ['session_id' => $session['id'] ?? null],
        );

        if ($invoice->organization_id) {
            AuditLogger::log(
                (int) $invoice->organization_id,
                null,
                'paid',
                'invoice',
                (int) $invoice->id,
                ['session_id' => $session['id'] ?? null],
            );
        }

        return ['handled' => true, 'notes' => 'legacy invoice marked paid'];
    }

    /**
     * Best-effort extraction of organization_id from a Stripe event payload.
     * Returns null if the customer cannot be resolved — never throws.
     *
     * @param  array<string, mixed>  $event
     */
    private function resolveOrganizationIdFromEvent(array $event): ?int
    {
        try {
            $object = (array) ($event['data']['object'] ?? []);
            $customerId = is_string($object['customer'] ?? null) ? trim($object['customer']) : null;

            if ($customerId === null || $customerId === '') {
                return null;
            }

            $orgId = Organization::query()
                ->where('stripe_id', $customerId)
                ->value('id');

            return $orgId !== null ? (int) $orgId : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
