<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class StoreDefenseScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\DefenseSchedule::class);
    }

    public function rules(): array
    {
        return [
            'thesis_id' => ['required', 'exists:theses,id'],
            'defense_room_id' => ['required', 'exists:defense_rooms,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:30', 'max:240'],
        ];
    }
}