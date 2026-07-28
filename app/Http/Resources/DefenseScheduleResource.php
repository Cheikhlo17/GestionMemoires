<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DefenseScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_at' => $this->scheduled_at,
            'ends_at' => $this->ends_at,
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'thesis' => [
                'id' => $this->thesis->id,
                'title' => $this->thesis->title,
                'student_name' => $this->thesis->student->user->full_name,
                'supervisor_name' => $this->thesis->supervisor?->user->full_name,
            ],
            'room' => new DefenseRoomResource($this->whenLoaded('room')),
            'jury_members' => JuryMemberResource::collection($this->whenLoaded('juryMembers')),
            'result' => new DefenseResultResource($this->whenLoaded('result')),
            'created_at' => $this->created_at,
        ];
    }
}