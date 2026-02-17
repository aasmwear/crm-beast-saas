<?php

namespace Database\Factories\Platform;

use App\Models\Organization;
use App\Models\Platform\OrganizationFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Platform\OrganizationFeature>
 */
class OrganizationFeatureFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OrganizationFeature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'features' => OrganizationFeature::DEFAULT_FEATURES,
            'subscription_status' => 'active',
            'trial_ends_at' => now()->addDays(14),
        ];
    }

    /**
     * Indicate that the subscription is in trial.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'active',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    /**
     * Indicate that the trial has ended.
     */
    public function trialEnded(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the subscription is past due.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'past_due',
        ]);
    }

    /**
     * Indicate that the subscription is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'cancelled',
        ]);
    }

    /**
     * Enable all features.
     */
    public function allFeatures(): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => [
                'attendance' => true,
                'sms' => true,
                'api_access' => true,
                'storage_gb' => 100,
            ],
        ]);
    }
}
