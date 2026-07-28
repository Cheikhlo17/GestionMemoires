<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\JuryMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JuryMemberFactory extends Factory
{
    protected $model = JuryMember::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(function () {
                return ['role_id' => Role::where('slug', 'jury-member')->first()?->id ?? Role::factory()];
            }),
            'department_id' => Department::factory(),
            'specialization' => fake()->words(3, true),
            'is_active' => true,
        ];
    }
}