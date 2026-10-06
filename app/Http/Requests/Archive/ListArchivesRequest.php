<?php

namespace App\Http\Requests\Archive;

use Illuminate\Foundation\Http\FormRequest;

class ListArchivesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'degree_level' => ['nullable', 'in:bachelor,master,phd'],
            'supervisor_id' => ['nullable', 'exists:supervisors,id'],
            'defense_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'visibility' => ['nullable', 'in:public,private'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}