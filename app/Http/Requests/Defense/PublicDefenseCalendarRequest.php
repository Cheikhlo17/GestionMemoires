<?php

namespace App\Http\Requests\Defense;

use Illuminate\Foundation\Http\FormRequest;

class PublicDefenseCalendarRequest extends FormRequest
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
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'room_id' => ['sometimes', 'integer', 'exists:defense_rooms,id'],
        ];
    }
}
