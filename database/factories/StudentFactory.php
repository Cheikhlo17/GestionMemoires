<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(function () {
                return ['role_id' => Role::where('slug', 'student')->first()?->id ?? Role::factory()];
            }),
            'department_id' => Department::factory(),
            'program_id' => Program::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'student_number' => 'STU-' . fake()->unique()->numerify('######'),
            'enrollment_date' => fake()->dateTimeBetween('-4 years', 'now'),
            'status' => fake()->randomElement(['active', 'graduated', 'suspended', 'withdrawn']),
        ];
    }
}