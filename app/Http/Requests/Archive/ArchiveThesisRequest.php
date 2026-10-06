<?php

namespace App\Http\Requests\Archive;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveThesisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\ThesisArchive::class);
    }

    public function rules(): array
    {
        return [
            'thesis_version_id' => ['nullable', 'exists:thesis_versions,id'],
            'keywords' => ['nullable', 'array', 'max:15'],
            'keywords.*' => ['string', 'max:50'],
            'visibility' => ['nullable', 'in:public,private'],
            'show_final_grade' => ['nullable', 'boolean'],
        ];
    }
}