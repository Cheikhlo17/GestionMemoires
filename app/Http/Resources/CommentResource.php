<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'thesis_version_id' => $this->thesis_version_id,
            'author' => [
                'id' => $this->author->id,
                'full_name' => $this->author->full_name,
                'role' => $this->author->role?->slug,
            ],
            'created_at' => $this->created_at,
        ];
    }
}