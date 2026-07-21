<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramFactory extends Factory
{
    protected $model = Program::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->unique()->jobTitle() . ' Program',
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'degree_level' => fake()->randomElement(['bachelor', 'master', 'phd']),
            'is_active' => true,
        ];
    }
}