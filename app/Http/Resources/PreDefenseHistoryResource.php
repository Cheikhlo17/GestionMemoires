<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreDefenseHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'passage_id' => $this->pre_defense_passage_id,
            'event' => $this->event,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'actor' => $this->whenLoaded('changedBy', fn () => [
                'id' => $this->changedBy->id,
                'full_name' => $this->changedBy->full_name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
