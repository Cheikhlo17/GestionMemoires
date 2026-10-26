<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupervisionMeetingActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'assigned_to' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee->id,
                'full_name' => $this->assignee->full_name,
            ]),
            'due_at' => $this->due_at,
            'status' => $this->status,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
