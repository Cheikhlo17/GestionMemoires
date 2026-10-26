<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class StoreThesisSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\ThesisSubject::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'problem_statement' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['nullable', 'string', 'max:10000'],
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*' => ['required', 'string', 'max:100'],
            'additional_information' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
