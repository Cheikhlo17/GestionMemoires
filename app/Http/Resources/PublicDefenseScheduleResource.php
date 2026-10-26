<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicDefenseScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_at' => $this->scheduled_at,
            'ends_at' => $this->ends_at,
            'thesis' => [
                'title' => $this->thesis->title,
                'department' => $this->thesis->department->name,
                'program' => $this->thesis->program->name,
            ],
            'room' => [
                'name' => $this->room->name,
                'building' => $this->room->building,
            ],
        ];
    }
}
