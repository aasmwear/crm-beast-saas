<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_signature_rejected(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test');

        $payload = json_encode([
            'id' => 'evt_invalid_sig',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => ['id' => 'sub_1', 'customer' => 'cus_1', 'status' => 'active']],
        ], JSON_THROW_ON_ERROR);

        $badHeader = $this->buildStripeSignatureHeader($payload, 'whsec_wrong');

        $response = $this->call(
            'POST',
            '/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $badHeader,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(400);
        $this->assertDatabaseCount('stripe_webhook_events', 0);
    }

    public function test_duplicate_event_is_ignored_safely(): void
    {
        $org = Organization::factory()->create(['stripe_id' => 'cus_dup']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        config()->set('services.stripe.webhook_secret', 'whsec_test');
        config()->set('billing.stripe_prices.pro', 'price_pro_123');

        $event = [
            'id' => 'evt_duplicate_1',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_dup',
                    'customer' => 'cus_dup',
                    'status' => 'active',
                    'current_period_end' => 1_925_000_000,
                    'items' => ['data' => [['price' => ['id' => 'price_pro_123']]]],
                ],
            ],
        ];

        $this->postSignedWebhook($event)->assertOk();
        $this->postSignedWebhook($event)->assertOk();

        $this->assertDatabaseCount('stripe_webhook_events', 1);
        $this->assertDatabaseHas('organization_subscriptions', [
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
        ]);
    }

    public function test_subscription_updated_syncs_canonical_status_and_period_end(): void
    {
        $org = Organization::factory()->create(['stripe_id' => 'cus_sync']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'seats_included' => 5,
        ]);

        config()->set('services.stripe.webhook_secret', 'whsec_test');
        config()->set('billing.stripe_prices.pro', 'price_pro_123');

        $event = [
            'id' => 'evt_sub_updated',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_sync',
                    'customer' => 'cus_sync',
                    'status' => 'trialing',
                    'current_period_end' => 1_925_000_100,
                    'trial_end' => 1_925_000_050,
                    'items' => ['data' => [['price' => ['id' => 'price_pro_123']]]],
                ],
            ],
        ];

        $this->postSignedWebhook($event)->assertOk();

        $sub = OrganizationSubscription::query()->where('organization_id', $org->id)->firstOrFail();
        $this->assertSame('pro', $sub->plan_key);
        $this->assertSame('trialing', $sub->status);
        $this->assertNotNull($sub->current_period_ends_at);
        $this->assertNotNull($sub->trial_ends_at);
    }

    public function test_subscription_deleted_marks_canonical_subscription_canceled(): void
    {
        $org = Organization::factory()->create(['stripe_id' => 'cus_cancel']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        config()->set('services.stripe.webhook_secret', 'whsec_test');
        config()->set('billing.stripe_prices.pro', 'price_pro_123');

        $event = [
            'id' => 'evt_sub_deleted',
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_cancel',
                    'customer' => 'cus_cancel',
                    'status' => 'canceled',
                    'items' => ['data' => [['price' => ['id' => 'price_pro_123']]]],
                ],
            ],
        ];

        $this->postSignedWebhook($event)->assertOk();

        $this->assertDatabaseHas('organization_subscriptions', [
            'organization_id' => $org->id,
            'status' => 'canceled',
        ]);
    }

    public function test_invoice_payment_failed_updates_status_to_past_due(): void
    {
        $org = Organization::factory()->create(['stripe_id' => 'cus_past_due']);
        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        config()->set('services.stripe.webhook_secret', 'whsec_test');

        $event = [
            'id' => 'evt_invoice_failed',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_123',
                    'customer' => 'cus_past_due',
                    'subscription' => 'sub_123',
                ],
            ],
        ];

        $this->postSignedWebhook($event)->assertOk();

        $this->assertDatabaseHas('organization_subscriptions', [
            'organization_id' => $org->id,
            'status' => 'past_due',
        ]);
    }

    public function test_checkout_session_completed_for_subscription_updates_canonical_record(): void
    {
        $org = Organization::factory()->create(['stripe_id' => 'cus_checkout']);

        config()->set('services.stripe.webhook_secret', 'whsec_test');
        config()->set('billing.stripe_prices.pro', 'price_pro_123');

        $event = [
            'id' => 'evt_checkout_completed_sub',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'mode' => 'subscription',
                    'payment_status' => 'paid',
                    'customer' => 'cus_checkout',
                    'metadata' => [
                        'plan_key' => 'pro',
                        'organization_id' => (string) $org->id,
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($event)->assertOk();

        $this->assertDatabaseHas('organization_subscriptions', [
            'organization_id' => $org->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function postSignedWebhook(array $event)
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $header = $this->buildStripeSignatureHeader($payload, $secret);

        return $this->call(
            'POST',
            '/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $header,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
    }

    private function buildStripeSignatureHeader(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return 't='.$timestamp.',v1='.$signature;
    }
}
