<?php

namespace App\Http\Requests\Supervision;

use App\Models\SupervisionMeeting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('meeting'));
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:480'],
            'mode' => ['sometimes', 'required', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'minutes' => ['nullable', 'string', 'max:10000'],
            'difficulties' => ['nullable', 'string', 'max:10000'],
            'decisions' => ['nullable', 'string', 'max:10000'],
            'status' => [
                'sometimes',
                Rule::in([
                    SupervisionMeeting::STATUS_SCHEDULED,
                    SupervisionMeeting::STATUS_COMPLETED,
                    SupervisionMeeting::STATUS_CANCELLED,
                ]),
            ],
        ];
    }
}
