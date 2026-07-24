<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThesisStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'remarks' => $this->remarks,
            'changed_by' => [
                'id' => $this->changedBy->id,
                'full_name' => $this->changedBy->full_name,
            ],
            'created_at' => $this->created_at,
        ];
    }
}