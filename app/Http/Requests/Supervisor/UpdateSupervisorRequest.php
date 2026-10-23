<?php

namespace App\Http\Requests\Supervisor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('supervisor'));
    }

    public function rules(): array
    {
        $userId = $this->route('supervisor')?->user_id;
        $isAdministrator = $this->user()->role?->slug === 'administrator';

        $rules = [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
        ];

        if ($isAdministrator) {
            return $rules + [
                'department_id' => ['sometimes', 'exists:departments,id'],
                'title' => ['nullable', 'string', 'max:50'],
                'specialization' => ['nullable', 'string', 'max:255'],
                'max_students' => ['sometimes', 'integer', 'min:1', 'max:50'],
                'is_active' => ['sometimes', 'boolean'],
            ];
        }

        return $rules + [
            'department_id' => ['prohibited'],
            'title' => ['prohibited'],
            'specialization' => ['prohibited'],
            'max_students' => ['prohibited'],
            'is_active' => ['prohibited'],
        ];
    }
}