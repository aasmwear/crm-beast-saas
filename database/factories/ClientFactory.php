<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
final class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * Digital Marketing Agency niches.
     *
     * @var array<int, string>
     */
    private const NICHES = [
        'Plumbers',
        'HVAC',
        'Electricians',
        'Roofers',
        'Dentists',
        'Chiropractors',
        'Law Firms',
        'Real Estate Agents',
        'Restaurants',
        'Auto Repair',
        'Landscaping',
        'Pest Control',
        'Locksmiths',
        'Veterinarians',
        'Medical Practices',
    ];

    /**
     * Industry categories.
     *
     * @var array<int, string>
     */
    private const INDUSTRIES = [
        'Home Services',
        'Healthcare',
        'Legal',
        'Real Estate',
        'Food & Beverage',
        'Automotive',
        'Professional Services',
        'Retail',
    ];

    /**
     * GBP Status values.
     *
     * @var array<int, string>
     */
    private const GBP_STATUSES = [
        'not_created',
        'created',
        'pending',
        'verified',
        'suspended',
    ];

    /**
     * GBP Access values.
     *
     * @var array<int, string>
     */
    private const GBP_ACCESS = [
        'no_access',
        'access_granted',
        'access_pending',
    ];

    /**
     * Client status values.
     *
     * @var array<int, string>
     */
    private const STATUSES = [
        'Lead',
        'Active',
        'Churned',
        'Paused',
    ];

    /**
     * Client activation statuses.
     *
     * @var array<int, string>
     */
    private const ACTIVATION_STATUSES = [
        'Inactive',
        'Active',
        'Paused',
        'Cancelled',
    ];

    public function definition(): array
    {
        $niche = $this->faker->randomElement(self::NICHES);
        $industry = $this->faker->randomElement(self::INDUSTRIES);
        $status = $this->faker->randomElement(self::STATUSES);
        $gbpStatus = $this->faker->randomElement(self::GBP_STATUSES);
        
        // GBP access depends on GBP status (can't have access if not created)
        $gbpAccess = $gbpStatus === 'not_created' 
            ? 'no_access' 
            : $this->faker->randomElement(self::GBP_ACCESS);

        return [
            'organization_id' => 1,
            'company_name' => $this->faker->company(),
            'industry' => $industry,
            'niche' => $niche,
            'primary_contact_name' => $this->faker->name(),
            'primary_contact_email' => $this->faker->unique()->safeEmail(),
            'primary_contact_phone' => $this->faker->phoneNumber(),
            'website' => $this->faker->optional(0.8)->url(),
            'address' => $this->faker->optional(0.7)->address(),
            'tags' => $this->faker->optional(0.6)->randomElements(['premium', 'high-value', 'referral', 'urgent'], $this->faker->numberBetween(1, 3)),
            'gbp_status' => $gbpStatus,
            'gbp_access' => $gbpAccess,
            'status' => $status,
            'client_activation_status' => $this->faker->randomElement(self::ACTIVATION_STATUSES),
            'notes_sales' => $this->faker->optional(0.5)->paragraph(),
            'notes_cst' => $this->faker->optional(0.4)->paragraph(),
            'notes_tech' => $this->faker->optional(0.3)->paragraph(),
            // Foreign keys (fronter_id, closer_id, assigned_account_manager_id) 
            // should be set explicitly when creating with relationships
        ];
    }

    /**
     * State for a Lead client.
     */
    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Lead',
            'client_activation_status' => 'Inactive',
            'gbp_status' => 'not_created',
            'gbp_access' => 'no_access',
        ]);
    }

    /**
     * State for an Active client with verified GBP.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Active',
            'client_activation_status' => 'Active',
            'gbp_status' => 'verified',
            'gbp_access' => 'access_granted',
        ]);
    }

    /**
     * State for a Churned client.
     */
    public function churned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Churned',
            'client_activation_status' => 'Cancelled',
        ]);
    }
}
