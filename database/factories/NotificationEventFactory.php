<?php

namespace Database\Factories;

use App\Models\NotificationEvent;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationEvent> */
final class NotificationEventFactory extends Factory
{
    protected $model = NotificationEvent::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'entity' => 'client',
            'entity_id' => 1,
            'type' => 'new_activity',
            'data' => [
                'message' => $this->faker->sentence(),
            ],
            'read_at' => null,
        ];
    }
}
