<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class AssignSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignSupervisor', $this->route('thesis'));
    }

    public function rules(): array
    {
        return [
            'supervisor_id' => ['required', 'exists:supervisors,id'],
        ];
    }
}