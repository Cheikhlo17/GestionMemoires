<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class CalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'room_id' => ['nullable', 'exists:defense_rooms,id'],
            'status' => ['nullable', 'in:scheduled,completed,cancelled,postponed'],
        ];
    }
}