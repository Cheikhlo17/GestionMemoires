<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('thesis'));
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:3000'],
            'thesis_version_id' => ['nullable', 'exists:thesis_versions,id'],
        ];
    }
}