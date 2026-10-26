<?php

namespace App\Http\Requests\Thesis;

use App\Models\ThesisCorrection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThesisCorrectionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $correction = $this->route('correction');

        return $correction instanceof ThesisCorrection
            && $this->user()->can('view', $correction);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    ThesisCorrection::STATUS_CORRECTED,
                    ThesisCorrection::STATUS_TO_CORRECT,
                    ThesisCorrection::STATUS_VALIDATED,
                ]),
            ],
            'remarks' => ['required', 'string', 'max:5000'],
        ];
    }
}
