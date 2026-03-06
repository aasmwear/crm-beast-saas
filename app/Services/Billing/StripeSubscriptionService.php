<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Organization;
use Carbon\CarbonInterface;

class StripeSubscriptionService
{
    /**
     * Start or switch the default Stripe subscription for an org.
     *
     * @param  array{success_url: string, cancel_url: string}  $urls
     * @return array{
     *   mode: 'checkout'|'created'|'swapped',
     *   checkout_url: string|null,
     *   status: string|null,
     *   trial_ends_at: CarbonInterface|null,
     *   current_period_ends_at: CarbonInterface|null
     * }
     */
    public function startForPlan(Organization $organization, string $priceId, array $urls): array
    {
        $organization->createOrGetStripeCustomer();

        $existing = $organization->subscription('default');

        if ($existing !== null && ($existing->active() || $existing->onTrial())) {
            $existing->noProrate()->swap($priceId);
            $swapped = $organization->subscription('default');

            return [
                'mode' => 'swapped',
                'checkout_url' => null,
                'status' => $swapped?->stripe_status,
                'trial_ends_at' => $swapped?->trial_ends_at,
                'current_period_ends_at' => $swapped?->ends_at,
            ];
        }

        if (! $organization->hasDefaultPaymentMethod()) {
            $checkout = $organization->newSubscription('default', $priceId)->checkout([
                'success_url' => $urls['success_url'],
                'cancel_url' => $urls['cancel_url'],
            ]);

            return [
                'mode' => 'checkout',
                'checkout_url' => $checkout->url,
                'status' => null,
                'trial_ends_at' => null,
                'current_period_ends_at' => null,
            ];
        }

        $created = $organization->newSubscription('default', $priceId)->create();

        return [
            'mode' => 'created',
            'checkout_url' => null,
            'status' => $created->stripe_status,
            'trial_ends_at' => $created->trial_ends_at,
            'current_period_ends_at' => $created->ends_at,
        ];
    }
}
