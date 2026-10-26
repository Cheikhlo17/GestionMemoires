<?php

namespace App\Http\Requests\Supervision;

use App\Models\SupervisionMeetingAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageActions', $this->route('meeting'));
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    SupervisionMeetingAction::STATUS_PENDING,
                    SupervisionMeetingAction::STATUS_IN_PROGRESS,
                    SupervisionMeetingAction::STATUS_COMPLETED,
                ]),
            ],
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
