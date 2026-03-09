<?php

namespace Tests\Feature\Observability;

use App\Models\Organization;
use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WebhookObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_signature_returns_400_and_logs_warning(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'stripe.webhook.signature_invalid'
                    && isset($context['error'])
                    && isset($context['ip']);
            });

        $payload = json_encode([
            'id' => 'evt_sig_test',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => []],
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
            $payload,
        );

        $response->assertStatus(400);
        $this->assertDatabaseCount('stripe_webhook_events', 0);
    }

    public function test_processing_failure_logs_structured_context_and_marks_event_failed(): void
    {
        $org = Organization::factory()->create(['stripe_id' => 'cus_obs_test']);

        config()->set('services.stripe.webhook_secret', 'whsec_test');

        $this->mock(\App\Services\Billing\StripeWebhookSyncService::class)
            ->shouldReceive('process')
            ->andThrow(new \RuntimeException('Test processing error'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($org) {
                return $message === 'Stripe webhook processing failed'
                    && ($context['stripe_event_id'] ?? null) === 'evt_process_fail'
                    && ($context['event_type'] ?? null) === 'customer.subscription.updated'
                    && ($context['organization_id'] ?? null) === $org->id
                    && ($context['exception_class'] ?? null) === 'RuntimeException';
            });

        $event = [
            'id' => 'evt_process_fail',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_obs',
                    'customer' => 'cus_obs_test',
                    'status' => 'active',
                ],
            ],
        ];

        $response = $this->postSignedWebhook($event);
        $response->assertStatus(422);

        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_process_fail',
            'status' => 'failed',
        ]);
    }

    public function test_missing_webhook_secret_logs_config_error(): void
    {
        config()->set('services.stripe.webhook_secret', '');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'stripe.webhook.config_missing'
                    && ($context['detail'] ?? null) === 'STRIPE_WEBHOOK_SECRET not configured';
            });

        $response = $this->postJson('/webhooks/stripe', ['id' => 'evt_no_secret']);
        $response->assertStatus(422);
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
            $payload,
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
