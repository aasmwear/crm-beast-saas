<?php

namespace Database\Factories;

use App\Models\ProjectMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectMessage> */
final class ProjectMessageFactory extends Factory
{
    protected $model = ProjectMessage::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'project_id' => 1,
            'user_id' => 1,
            'body' => $this->faker->paragraph(),
            'attachments' => [],
        ];
    }
}
