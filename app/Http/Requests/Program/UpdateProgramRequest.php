<?php

namespace App\Http\Requests\Program;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role?->slug === 'administrator';
    }

    public function rules(): array
    {
        $programId = $this->route('program')?->id;

        return [
            'department_id' => ['sometimes', 'exists:departments,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('programs', 'code')->ignore($programId)],
            'degree_level' => ['sometimes', 'in:bachelor,master,phd'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}