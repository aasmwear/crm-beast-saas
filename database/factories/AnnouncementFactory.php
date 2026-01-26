<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Announcement> */
final class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            // Callers can override organization_id/author_id as needed.
            'organization_id' => 1,
            'author_id' => 1,
            'title' => $this->faker->sentence(6),
            'body' => $this->faker->paragraph(),
            'pinned' => false,
        ];
    }
}
