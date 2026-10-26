<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThesisCorrectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'thesis_id' => $this->thesis_id,
            'thesis_version' => $this->whenLoaded('version', fn () => [
                'id' => $this->version->id,
                'version_number' => $this->version->version_number,
                'file_name' => $this->version->file_name,
            ]),
            'page_number' => $this->page_number,
            'section' => $this->section,
            'anchor' => $this->anchor,
            'remark' => $this->remark,
            'priority' => $this->priority,
            'status' => $this->status,
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'full_name' => $this->author->full_name,
            ]),
            'resolver' => $this->whenLoaded('resolver', fn () => $this->resolver ? [
                'id' => $this->resolver->id,
                'full_name' => $this->resolver->full_name,
            ] : null),
            'resolved_at' => $this->resolved_at,
            'history' => ThesisCorrectionHistoryResource::collection($this->whenLoaded('history')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
