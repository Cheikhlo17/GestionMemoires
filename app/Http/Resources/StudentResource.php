<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_number' => $this->student_number,
            'enrollment_date' => $this->enrollment_date,
            'status' => $this->status,
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'full_name' => $this->user->full_name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'program' => new ProgramResource($this->whenLoaded('program')),
            'academic_year' => new AcademicYearResource($this->whenLoaded('academicYear')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}