<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDefenseScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('defense_schedule'));
    }

    public function rules(): array
    {
        return [
            'defense_room_id' => ['sometimes', 'exists:defense_rooms,id'],
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'integer', 'min:30', 'max:240'],
        ];
    }
}