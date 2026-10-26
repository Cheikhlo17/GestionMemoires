<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreDefensePassageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'scheduled_at' => $this->scheduled_at,
            'ends_at' => $this->ends_at,
            'duration_minutes' => $this->duration_minutes,
            'thesis' => [
                'id' => $this->thesis->id,
                'title' => $this->thesis->title,
            ],
            'student' => [
                'id' => $this->student->id,
                'full_name' => $this->student->user->full_name,
                'student_number' => $this->student->student_number,
            ],
            'observations' => $this->observations,
            'recommendations' => $this->recommendations,
            'outcome' => $this->outcome,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
