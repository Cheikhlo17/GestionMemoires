<?php

namespace App\Repositories;

use App\Models\Student;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentRepository implements StudentRepositoryInterface
{
    public function __construct(protected Student $model)
    {
    }

    public function paginate(int $perPage, array $filters): LengthAwarePaginator
    {
        $query = $this->model->with(['user', 'department', 'program', 'academicYear']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        if (!empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['sort_by'])) {
            $direction = $filters['sort_direction'] ?? 'asc';
            $query->orderBy($filters['sort_by'], $direction);
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Student
    {
        return $this->model->with(['user', 'department', 'program', 'academicYear'])->find($id);
    }

    public function create(array $data): Student
    {
        return $this->model->create($data);
    }

    public function update(Student $student, array $data): Student
    {
        $student->update($data);

        return $student->fresh(['user', 'department', 'program', 'academicYear']);
    }

    public function delete(Student $student): bool
    {
        return (bool) $student->delete();
    }

    public function studentNumberExists(string $studentNumber, ?int $exceptId = null): bool
    {
        return $this->model
            ->where('student_number', $studentNumber)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }
}