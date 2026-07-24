<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class UploadVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('uploadVersion', $this->route('thesis'));
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,docx,zip', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}