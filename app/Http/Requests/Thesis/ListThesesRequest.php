<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class ListThesesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\Thesis::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,submitted,under_review,revision_required,approved,rejected,archived'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'supervisor_id' => ['nullable', 'exists:supervisors,id'],
            'student_id' => ['nullable', 'exists:students,id'],
            'sort_by' => ['nullable', 'in:title,status,submitted_at,created_at'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}