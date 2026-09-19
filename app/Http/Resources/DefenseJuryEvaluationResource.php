<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DefenseJuryEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grade' => $this->grade,
            'verdict' => $this->verdict,
            'remarks' => $this->remarks,
            'submitted_at' => $this->submitted_at,
            'jury_member' => [
                'id' => $this->juryMember->id,
                'full_name' => $this->juryMember->user->full_name,
            ],
        ];
    }
}