<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attendance> */
final class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'user_id' => 1,
            'clock_in_at' => now(),
        ];
    }
}
