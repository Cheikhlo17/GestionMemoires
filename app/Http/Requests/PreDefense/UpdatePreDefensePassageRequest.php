<?php

namespace App\Http\Requests\PreDefense;

use App\Models\PreDefensePassage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreDefensePassageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'position' => ['sometimes', 'integer', 'min:1'],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:240'],
            'observations' => ['nullable', 'string', 'max:10000'],
            'recommendations' => ['nullable', 'string', 'max:10000'],
            'outcome' => [
                'sometimes',
                Rule::in([
                    PreDefensePassage::OUTCOME_PENDING,
                    PreDefensePassage::OUTCOME_READY,
                    PreDefensePassage::OUTCOME_NEEDS_REVISION,
                ]),
            ],
        ];
    }
}
