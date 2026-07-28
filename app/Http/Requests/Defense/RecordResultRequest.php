<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class RecordResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordResult', $this->route('defense_schedule'));
    }

    public function rules(): array
    {
        return [
            'final_grade' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'verdict' => ['required', 'in:pass,pass_with_revisions,fail'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}