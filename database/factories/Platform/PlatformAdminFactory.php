<?php

namespace Database\Factories\Platform;

use App\Models\Platform\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Platform\PlatformAdmin>
 */
class PlatformAdminFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PlatformAdmin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'avatar_path' => null,
            'role' => 'support',
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the platform admin is a Super Admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
        ]);
    }

    /**
     * Indicate that the platform admin is Support staff.
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'support',
        ]);
    }

    /**
     * Indicate that the platform admin is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
