<?php

namespace App\Http\Resources;

use App\Models\PreDefensePassage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreDefenseSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $passages = $this->whenLoaded('passages');
        $history = $this->whenLoaded('history');

        if ($request->user()?->role?->slug === 'student') {
            $userId = $request->user()->id;
            if ($passages instanceof \Illuminate\Support\Collection) {
                $passages = $passages->filter(fn (PreDefensePassage $passage) => (int) $passage->student->user_id === (int) $userId);
            }
            if ($history instanceof \Illuminate\Support\Collection) {
                $visiblePassageIds = $passages instanceof \Illuminate\Support\Collection
                    ? $passages->pluck('id')->all()
                    : [];
                $history = $history->filter(fn ($entry) => $entry->pre_defense_passage_id === null
                    || in_array($entry->pre_defense_passage_id, $visiblePassageIds, true));
            }
        }

        return [
            'id' => $this->id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'responsible' => $this->whenLoaded('responsible', fn () => [
                'id' => $this->responsible->id,
                'full_name' => $this->responsible->full_name,
            ]),
            'location' => $this->location,
            'scheduled_at' => $this->scheduled_at,
            'ends_at' => $this->ends_at,
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'completed_at' => $this->completed_at,
            'passages' => PreDefensePassageResource::collection($passages),
            'history' => PreDefenseHistoryResource::collection($history),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
