<?php

namespace App\Http\Requests\PreDefense;

use App\Models\PreDefenseSession;
use Illuminate\Foundation\Http\FormRequest;

class StorePreDefenseSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PreDefenseSession::class);
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'location' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:480'],
        ];
    }
}
