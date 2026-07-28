<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DefenseResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'final_grade' => $this->final_grade,
            'verdict' => $this->verdict,
            'remarks' => $this->remarks,
            'recorded_at' => $this->recorded_at,
        ];
    }
}