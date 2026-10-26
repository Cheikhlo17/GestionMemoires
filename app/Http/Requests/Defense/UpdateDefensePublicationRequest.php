<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDefensePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->route('defense_schedule'));
    }

    public function rules(): array
    {
        return [
            'is_public' => ['required', 'boolean'],
        ];
    }
}
