<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupervisionMeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'thesis_id' => $this->thesis_id,
            'scheduled_at' => $this->scheduled_at,
            'ends_at' => $this->ends_at,
            'duration_minutes' => $this->duration_minutes,
            'mode' => $this->mode,
            'location' => $this->location,
            'agenda' => $this->agenda,
            'minutes' => $this->minutes,
            'difficulties' => $this->difficulties,
            'decisions' => $this->decisions,
            'status' => $this->status,
            'completed_at' => $this->completed_at,
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($participant) => [
                'id' => $participant->id,
                'full_name' => $participant->full_name,
            ])),
            'actions' => SupervisionMeetingActionResource::collection($this->whenLoaded('actions')),
            'history' => SupervisionMeetingHistoryResource::collection($this->whenLoaded('history')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
