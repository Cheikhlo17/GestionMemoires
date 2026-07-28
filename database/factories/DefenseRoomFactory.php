<?php

namespace Database\Factories;

use App\Models\DefenseRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class DefenseRoomFactory extends Factory
{
    protected $model = DefenseRoom::class;

    public function definition(): array
    {
        return [
            'name' => 'Room ' . fake()->unique()->numberBetween(100, 599),
            'building' => fake()->randomElement(['Main Building', 'Engineering Block', 'Science Block']),
            'capacity' => fake()->numberBetween(6, 30),
            'is_active' => true,
        ];
    }
}