<?php

namespace App\Http\Requests\Archive;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ArchiveThesisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('archive', [\App\Models\ThesisArchive::class, $this->route('thesis')]);
    }

    public function rules(): array
    {
        $thesis = $this->route('thesis');

        return [
            'thesis_version_id' => [
                'nullable',
                Rule::exists('thesis_versions', 'id')
                    ->where('thesis_id', $thesis?->id)
                    ->where('mime_type', 'application/pdf'),
            ],
            'keywords' => ['nullable', 'array', 'max:15'],
            'keywords.*' => ['string', 'max:50'],
            'visibility' => ['nullable', 'in:public,private'],
            'show_final_grade' => ['nullable', 'boolean'],
        ];
    }
}