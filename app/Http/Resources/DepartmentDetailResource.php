<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'is_active' => $this->is_active,
            'head_of_department' => $this->whenLoaded('headOfDepartment', fn () => $this->headOfDepartment ? [
                'id' => $this->headOfDepartment->id,
                'full_name' => $this->headOfDepartment->full_name,
            ] : null),
            'programs_count' => $this->whenCounted('programs'),
            'students_count' => $this->whenCounted('students'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}