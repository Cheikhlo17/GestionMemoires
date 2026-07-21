<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('student'));
    }

    public function rules(): array
    {
        $studentId = $this->route('student')?->id;
        $userId = $this->route('student')?->user_id;

        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['sometimes', 'exists:departments,id'],
            'program_id' => ['sometimes', 'exists:programs,id'],
            'academic_year_id' => ['sometimes', 'exists:academic_years,id'],
            'student_number' => ['sometimes', 'string', 'max:50', Rule::unique('students', 'student_number')->ignore($studentId)],
            'enrollment_date' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:active,graduated,suspended,withdrawn'],
        ];
    }
}