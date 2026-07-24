<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class ChangeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('thesis'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:submitted,under_review,revision_required,approved,rejected,archived'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}