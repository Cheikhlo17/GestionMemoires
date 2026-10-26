<?php

namespace App\Http\Requests\Thesis;

use App\Models\ThesisSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewThesisSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('thesisSubject'));
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    ThesisSubject::STATUS_UNDER_REVIEW,
                    ThesisSubject::STATUS_ACCEPTED,
                    ThesisSubject::STATUS_REJECTED,
                ]),
            ],
            'remarks' => ['required_if:status,' . ThesisSubject::STATUS_REJECTED, 'nullable', 'string', 'max:5000'],
        ];
    }
}
