<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 years', 'now');
        $end = (clone $start)->modify('+1 year');

        return [
            'label' => $start->format('Y') . '-' . $end->format('Y'),
            'start_date' => $start,
            'end_date' => $end,
            'is_current' => false,
        ];
    }
}