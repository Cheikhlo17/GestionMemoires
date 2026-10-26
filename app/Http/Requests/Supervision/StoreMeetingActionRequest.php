<?php

namespace App\Http\Requests\Supervision;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageActions', $this->route('meeting'));
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'description' => ['required', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
