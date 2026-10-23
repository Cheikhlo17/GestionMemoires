<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class AssignJuryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignJury', $this->route('defense_schedule'));
    }

    public function rules(): array
    {
        return [
            'jury' => ['required', 'array', 'size:3'],
            'jury.*.jury_member_id' => ['required', 'distinct', 'exists:jury_members,id'],
            'jury.*.role' => ['required', 'distinct', 'in:president,examiner,reporter'],
        ];
    }
}