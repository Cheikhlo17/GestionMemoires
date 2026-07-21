<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Student::class);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'department_id' => ['required', 'exists:departments,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'student_number' => ['required', 'string', 'max:50', 'unique:students,student_number'],
            'enrollment_date' => ['required', 'date'],
            'status' => ['nullable', 'in:active,graduated,suspended,withdrawn'],
        ];
    }
}