<?php

namespace App\Http\Requests\PreDefense;

use App\Models\PreDefenseSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreDefenseSessionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([PreDefenseSession::STATUS_COMPLETED, PreDefenseSession::STATUS_CANCELLED]),
            ],
        ];
    }
}
