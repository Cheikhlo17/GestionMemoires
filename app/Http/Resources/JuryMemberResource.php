<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JuryMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->user->full_name,
            'specialization' => $this->specialization,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'role' => $this->whenPivotLoaded('defense_jury_members', fn () => $this->pivot->role),
        ];
    }
}