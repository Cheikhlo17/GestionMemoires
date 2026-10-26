<?php

namespace App\Http\Requests\PreDefense;

use Illuminate\Foundation\Http\FormRequest;

class StorePreDefensePassageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'thesis_id' => ['required', 'integer', 'exists:theses,id'],
            'position' => ['required', 'integer', 'min:1'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:240'],
            'observations' => ['nullable', 'string', 'max:10000'],
            'recommendations' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
