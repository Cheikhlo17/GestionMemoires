<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThesisArchivePublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = $this->defenseSchedule?->result;

        return [
            'id' => $this->id,
            'title' => $this->thesis->title,
            'abstract' => $this->thesis->abstract,
            'keywords' => $this->keywords,
            'student_name' => $this->thesis->student->user->full_name,
            'supervisor_name' => $this->thesis->supervisor?->user->full_name,
            'department' => $this->thesis->department->name,
            'program' => $this->thesis->program->name,
            'degree_level' => $this->thesis->program->degree_level,
            'academic_year' => $this->thesis->academicYear->label,
            'jury_members' => $this->defenseSchedule
                ? $this->defenseSchedule->juryMembers->map(fn ($j) => $j->user->full_name)
                : [],
            'defense_date' => $this->defenseSchedule?->scheduled_at,
            'final_grade' => $this->show_final_grade ? ($result?->final_grade) : null,
            'archived_at' => $this->archived_at,
            'can_download' => $this->isPublic(),
        ];
    }
}