<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThesisSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('thesisSubject'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'problem_statement' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['nullable', 'string', 'max:10000'],
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*' => ['required', 'string', 'max:100'],
            'additional_information' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
