<?php

namespace App\Http\Requests\PreDefense;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreDefenseSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'responsible_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:480'],
        ];
    }
}
