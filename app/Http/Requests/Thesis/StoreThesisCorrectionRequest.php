<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreThesisCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createCorrection', $this->route('thesis'));
    }

    public function rules(): array
    {
        $thesis = $this->route('thesis');

        return [
            'thesis_version_id' => [
                'required',
                Rule::exists('thesis_versions', 'id')->where('thesis_id', $thesis?->id),
            ],
            'page_number' => ['nullable', 'integer', 'min:1'],
            'section' => ['nullable', 'string', 'max:255'],
            'anchor' => ['nullable', 'string', 'max:255'],
            'remark' => ['required', 'string', 'max:5000'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high'],
        ];
    }
}
