<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThesisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'abstract' => $this->abstract,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
            'approved_at' => $this->approved_at,
            'student' => [
                'id' => $this->student->id,
                'full_name' => $this->student->user->full_name,
                'student_number' => $this->student->student_number,
            ],
            'supervisor' => $this->when($this->supervisor, fn () => new SupervisorMiniResource($this->supervisor)),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'program' => new ProgramResource($this->whenLoaded('program')),
            'academic_year' => new AcademicYearResource($this->whenLoaded('academicYear')),
            'versions' => ThesisVersionResource::collection($this->whenLoaded('versions')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'status_histories' => ThesisStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}