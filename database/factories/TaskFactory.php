<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
final class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'project_id' => 1,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'assignees' => [],
            'due_date' => $this->faker->dateTimeBetween('now', '+2 months'),
            'priority' => $this->faker->randomElement(['Low', 'Medium', 'High']),
            'status' => $this->faker->randomElement(['Open', 'In Progress', 'Done']),
            'estimated_hours' => $this->faker->randomFloat(1, 1, 40),
            'logged_hours' => 0.0,
            'subtasks' => [],
            'attachments' => [],
            'comments' => [],
            'submission' => [],
            'review_status' => 'Pending',
        ];
    }
}
