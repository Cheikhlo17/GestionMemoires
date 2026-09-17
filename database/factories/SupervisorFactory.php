<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Role;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupervisorFactory extends Factory
{
    protected $model = Supervisor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(function () {
                return ['role_id' => Role::where('slug', 'supervisor')->first()?->id ?? Role::factory()];
            }),
            'department_id' => Department::factory(),
            'title' => fake()->randomElement(['Dr.', 'Prof.', 'Assoc. Prof.']),
            'specialization' => fake()->words(3, true),
            'max_students' => fake()->numberBetween(3, 10),
            'is_active' => true,
        ];
    }
}