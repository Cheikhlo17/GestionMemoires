<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThesisSubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'problem_statement' => $this->problem_statement,
            'objectives' => $this->objectives,
            'keywords' => $this->keywords ?? [],
            'additional_information' => $this->additional_information,
            'status' => $this->status,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at,
            'decision_reason' => $this->decision_reason,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'full_name' => $this->student->user->full_name,
            ] : null),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'full_name' => $this->reviewer->full_name,
            ] : null),
            'history' => ThesisSubjectHistoryResource::collection($this->whenLoaded('history')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
