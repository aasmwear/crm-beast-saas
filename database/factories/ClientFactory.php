<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
final class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'company_name' => $this->faker->company(),
            'primary_contact_name' => $this->faker->name(),
            'primary_contact_email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
