<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class PublishThesisSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'problem_statement' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['nullable', 'string', 'max:10000'],
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*' => ['required', 'string', 'max:100'],
            'additional_information' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
