<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Services\AuditLogger;
use App\Support\PlanCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StripeWebhookSyncService
{
    /**
     * @param  array<string, mixed>  $event
     * @return array{handled: bool, notes: string}
     */
    public function process(array $event): array
    {
        $type = (string) ($event['type'] ?? '');
        $object = (array) (($event['data']['object'] ?? []));

        return match ($type) {
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->syncFromSubscriptionEvent($type, $object),
            'checkout.session.completed' => $this->syncFromCheckoutCompleted($object),
            'invoice.payment_succeeded' => $this->syncFromInvoicePayment($type, $object),
            'invoice.payment_failed' => $this->syncFromInvoicePayment($type, $object),
            default => ['handled' => false, 'notes' => 'event ignored'],
        };
    }

    /**
     * @param  array<string, mixed>  $subscription
     * @return array{handled: bool, notes: string}
     */
    private function syncFromSubscriptionEvent(string $type, array $subscription): array
    {
        $customerId = $this->stringOrNull($subscription['customer'] ?? null);
        $organization = $this->findOrganizationByCustomer($customerId);

        if ($organization === null) {
            return ['handled' => false, 'notes' => 'organization not found for customer'];
        }

        $status = $type === 'customer.subscription.deleted'
            ? OrganizationSubscription::STATUS_CANCELED
            : (string) ($subscription['status'] ?? OrganizationSubscription::STATUS_ACTIVE);

        $priceId = $this->extractPriceIdFromSubscription($subscription);
        $mappedPlanKey = $this->planKeyFromPriceId($priceId);

        $sub = $organization->billingSubscription;
        $resolvedPlanKey = $mappedPlanKey
            ?? $sub?->plan_key
            ?? $this->safeLegacyPlanKey($organization)
            ?? PlanCatalog::defaultPlanKey();

        if ($priceId !== null && $mappedPlanKey === null) {
            Log::warning('Stripe webhook price-to-plan mismatch', [
                'organization_id' => $organization->id,
                'customer_id' => $customerId,
                'price_id' => $priceId,
                'event_type' => $type,
            ]);
        }

        $changes = $this->upsertCanonicalSubscription(
            $organization,
            $resolvedPlanKey,
            $status,
            $this->timestampOrNull($subscription['current_period_end'] ?? null),
            $this->timestampOrNull($subscription['trial_end'] ?? null),
        );

        if ($priceId !== null && $mappedPlanKey === null) {
            AuditLogger::log(
                $organization,
                null,
                'webhook_plan_mismatch',
                'subscription',
                $changes['subscription_id'],
                ['price_id' => $priceId, 'event_type' => $type],
            );
        }

        $this->logStatusAudit($organization, $changes['subscription_id'], $changes['before_status'], $changes['after_status'], $type);

        if ($type === 'customer.subscription.deleted') {
            AuditLogger::log(
                $organization,
                null,
                'subscription_canceled',
                'subscription',
                $changes['subscription_id'],
                ['event_type' => $type],
            );
        }

        return ['handled' => true, 'notes' => 'subscription synced'];
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array{handled: bool, notes: string}
     */
    private function syncFromCheckoutCompleted(array $session): array
    {
        $mode = (string) ($session['mode'] ?? '');
        if ($mode !== 'subscription') {
            return ['handled' => false, 'notes' => 'checkout completed not in subscription mode'];
        }

        $customerId = $this->stringOrNull($session['customer'] ?? null);
        $organization = $this->findOrganizationByCustomer($customerId);

        if ($organization === null) {
            return ['handled' => false, 'notes' => 'organization not found for customer'];
        }

        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $metadataPlan = $this->stringOrNull($metadata['plan_key'] ?? null);
        $resolvedPlanKey = ($metadataPlan !== null && PlanCatalog::isValidPlanKey($metadataPlan))
            ? $metadataPlan
            : ($organization->billingSubscription?->plan_key
                ?? $this->safeLegacyPlanKey($organization)
                ?? PlanCatalog::defaultPlanKey());

        $paymentStatus = (string) ($session['payment_status'] ?? '');
        $status = in_array($paymentStatus, ['paid', 'no_payment_required'], true)
            ? OrganizationSubscription::STATUS_ACTIVE
            : OrganizationSubscription::STATUS_INCOMPLETE;

        $changes = $this->upsertCanonicalSubscription(
            $organization,
            $resolvedPlanKey,
            $status,
            null,
            null,
        );

        $this->logStatusAudit(
            $organization,
            $changes['subscription_id'],
            $changes['before_status'],
            $changes['after_status'],
            'checkout.session.completed',
        );

        return ['handled' => true, 'notes' => 'checkout subscription synced'];
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array{handled: bool, notes: string}
     */
    private function syncFromInvoicePayment(string $type, array $invoice): array
    {
        $customerId = $this->stringOrNull($invoice['customer'] ?? null);
        $organization = $this->findOrganizationByCustomer($customerId);

        if ($organization === null) {
            return ['handled' => false, 'notes' => 'organization not found for customer'];
        }

        $sub = $organization->billingSubscription;
        $resolvedPlanKey = $sub?->plan_key
            ?? $this->safeLegacyPlanKey($organization)
            ?? PlanCatalog::defaultPlanKey();

        $beforeStatus = $sub?->status;
        $nextStatus = $beforeStatus;
        if ($type === 'invoice.payment_succeeded') {
            if ($beforeStatus !== OrganizationSubscription::STATUS_CANCELED) {
                $nextStatus = OrganizationSubscription::STATUS_ACTIVE;
            }
        } else {
            if (! in_array((string) $beforeStatus, [OrganizationSubscription::STATUS_CANCELED, OrganizationSubscription::STATUS_UNPAID], true)) {
                $nextStatus = OrganizationSubscription::STATUS_PAST_DUE;
            } elseif ($beforeStatus === null) {
                $nextStatus = OrganizationSubscription::STATUS_PAST_DUE;
            }
        }

        $periodEnd = $this->extractPeriodEndFromInvoice($invoice);
        $changes = $this->upsertCanonicalSubscription(
            $organization,
            $resolvedPlanKey,
            (string) ($nextStatus ?? OrganizationSubscription::STATUS_ACTIVE),
            $periodEnd,
            null,
        );

        $this->logStatusAudit($organization, $changes['subscription_id'], $changes['before_status'], $changes['after_status'], $type);

        AuditLogger::log(
            $organization,
            null,
            $type === 'invoice.payment_succeeded' ? 'payment_succeeded' : 'payment_failed',
            'subscription',
            $changes['subscription_id'],
            ['event_type' => $type, 'status' => $changes['after_status']],
        );

        return ['handled' => true, 'notes' => 'invoice payment synced'];
    }

    /**
     * @return array{subscription_id: int, before_status: string|null, after_status: string}
     */
    private function upsertCanonicalSubscription(
        Organization $organization,
        string $planKey,
        string $status,
        ?Carbon $currentPeriodEndsAt,
        ?Carbon $trialEndsAt,
    ): array {
        $existing = $organization->billingSubscription;
        $beforeStatus = $existing?->status;

        $planDefaults = PlanCatalog::get($planKey);
        $seatsIncluded = $planDefaults['seats_included']
            ?? $existing?->seats_included
            ?? 5;

        $sub = OrganizationSubscription::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'plan_key' => $planKey,
                'status' => $status,
                'seats_included' => $seatsIncluded,
                'current_period_ends_at' => $currentPeriodEndsAt,
                'trial_ends_at' => $trialEndsAt,
            ],
        );

        return [
            'subscription_id' => (int) $sub->id,
            'before_status' => $beforeStatus,
            'after_status' => (string) $sub->status,
        ];
    }

    private function logStatusAudit(
        Organization $organization,
        int $subscriptionId,
        ?string $beforeStatus,
        string $afterStatus,
        string $eventType,
    ): void {
        if ($beforeStatus === $afterStatus) {
            return;
        }

        AuditLogger::log(
            $organization,
            null,
            'subscription_status_changed',
            'subscription',
            $subscriptionId,
            [
                'from' => $beforeStatus,
                'to' => $afterStatus,
                'event_type' => $eventType,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function extractPriceIdFromSubscription(array $subscription): ?string
    {
        $items = is_array($subscription['items'] ?? null) ? $subscription['items'] : null;
        $data = is_array($items['data'] ?? null) ? $items['data'] : [];
        $firstItem = is_array($data[0] ?? null) ? $data[0] : null;
        $price = is_array($firstItem['price'] ?? null) ? $firstItem['price'] : null;

        return $this->stringOrNull($price['id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function extractPeriodEndFromInvoice(array $invoice): ?Carbon
    {
        $lines = is_array($invoice['lines'] ?? null) ? $invoice['lines'] : null;
        $data = is_array($lines['data'] ?? null) ? $lines['data'] : [];
        $firstLine = is_array($data[0] ?? null) ? $data[0] : null;
        $period = is_array($firstLine['period'] ?? null) ? $firstLine['period'] : null;
        $periodEnd = $period['end'] ?? null;

        return $this->timestampOrNull($periodEnd);
    }

    private function planKeyFromPriceId(?string $priceId): ?string
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        $map = (array) config('billing.stripe_prices', []);
        foreach ($map as $planKey => $mappedPriceId) {
            if (! is_string($mappedPriceId) || trim($mappedPriceId) === '') {
                continue;
            }
            if (trim($mappedPriceId) === $priceId && PlanCatalog::isValidPlanKey((string) $planKey)) {
                return (string) $planKey;
            }
        }

        return null;
    }

    private function safeLegacyPlanKey(Organization $organization): ?string
    {
        $candidate = is_string($organization->plan ?? null) ? (string) $organization->plan : null;

        return ($candidate !== null && PlanCatalog::isValidPlanKey($candidate)) ? $candidate : null;
    }

    private function findOrganizationByCustomer(?string $customerId): ?Organization
    {
        if ($customerId === null || $customerId === '') {
            return null;
        }

        return Organization::query()->where('stripe_id', $customerId)->first();
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function timestampOrNull(mixed $value): ?Carbon
    {
        if (is_int($value)) {
            return Carbon::createFromTimestampUTC($value);
        }

        if (is_string($value) && ctype_digit($value)) {
            return Carbon::createFromTimestampUTC((int) $value);
        }

        return null;
    }
}
