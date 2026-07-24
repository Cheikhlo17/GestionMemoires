<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThesisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('thesis'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'abstract' => ['nullable', 'string', 'max:5000'],
        ];
    }
}