<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Department> */
final class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'name' => $this->faker->word().' Dept',
            'code' => strtoupper($this->faker->lexify('???')),
        ];
    }
}
