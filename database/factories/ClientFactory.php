<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Client> */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'organization_id' => null, // set in seeder
            'company_name' => $this->faker->company(),
            'industry' => $this->faker->randomElement(['SEO', 'PPC', 'SMM']),
            'niche' => $this->faker->word(),
            'primary_contact_name' => $this->faker->name(),
            'primary_contact_email' => $this->faker->safeEmail(),
            'primary_contact_phone' => $this->faker->phoneNumber(),
            'website' => $this->faker->url(),
            'address' => $this->faker->address(),
            'tags' => [],
            'fronter' => [],
            'closer' => [],
            'assigned_account_manager_id' => null,
            'google_business_profile_status' => 'Not Created',
            'google_business_profile_access_status' => 'No Access',
            'client_activation_status' => 'Inactive',
            'notes_by_cst' => null,
            'notes_by_sales' => null,
            'notes_by_tech' => null,
            'status' => 'active',
        ];
    }
}
