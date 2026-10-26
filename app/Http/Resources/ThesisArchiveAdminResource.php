<?php

namespace App\Http\Resources;

use App\Support\GradeMentionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThesisArchiveAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = $this->defenseSchedule?->result;

        return [
            'id' => $this->id,
            'thesis_id' => $this->thesis_id,
            'title' => $this->thesis->title,
            'abstract' => $this->thesis->abstract,
            'keywords' => $this->keywords,
            'visibility' => $this->visibility,
            'show_final_grade' => $this->show_final_grade,
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
            'final_grade' => $result?->final_grade,
            'mention' => GradeMentionResolver::forGrade($result?->final_grade),
            'verdict' => $result?->verdict,
            'final_version' => [
                'id' => $this->finalVersion->id,
                'file_name' => $this->finalVersion->file_name,
            ],
            'archived_by' => $this->archivedBy->full_name,
            'archived_at' => $this->archived_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}