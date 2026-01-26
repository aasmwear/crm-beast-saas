<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'client_id' => 1,
            'title' => $this->faker->sentence(4),
            'status' => 'Active',
        ];
    }
}
