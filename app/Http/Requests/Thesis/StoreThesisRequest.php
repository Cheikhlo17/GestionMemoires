<?php

namespace App\Http\Requests\Thesis;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreThesisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Thesis::class);
    }

    public function rules(): array
    {
        $studentId = Student::where('user_id', $this->user()->id)->value('id');

        return [
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['required', 'exists:departments,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'thesis_subject_id' => [
                'required',
                Rule::exists('thesis_subjects', 'id')
                    ->where('student_id', $studentId)
                    ->where('department_id', $this->input('department_id'))
                    ->where('status', 'accepted'),
            ],
        ];
    }
}