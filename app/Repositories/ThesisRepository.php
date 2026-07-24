<?php

namespace App\Repositories;

use App\Models\Thesis;
use App\Repositories\Interfaces\ThesisRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ThesisRepository implements ThesisRepositoryInterface
{
    public function __construct(protected Thesis $model)
    {
    }

    public function paginate(int $perPage, array $filters): LengthAwarePaginator
    {
        $query = $this->model->with([
            'student.user',
            'supervisor.user',
            'department',
            'program',
            'academicYear',
        ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('student.user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['supervisor_id'])) {
            $query->where('supervisor_id', $filters['supervisor_id']);
        }

        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (!empty($filters['sort_by'])) {
            $direction = $filters['sort_direction'] ?? 'asc';
            $query->orderBy($filters['sort_by'], $direction);
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Thesis
    {
        return $this->model->with([
            'student.user',
            'supervisor.user',
            'department',
            'program',
            'academicYear',
            'versions.uploader',
            'comments.author',
            'statusHistories.changedBy',
        ])->find($id);
    }

    public function findByStudentId(int $studentId): ?Thesis
    {
        return $this->model->where('student_id', $studentId)->first();
    }

    public function create(array $data): Thesis
    {
        return $this->model->create($data);
    }

    public function update(Thesis $thesis, array $data): Thesis
    {
        $thesis->update($data);

        return $thesis->fresh(['student.user', 'supervisor.user', 'department', 'program', 'academicYear']);
    }

    public function delete(Thesis $thesis): bool
    {
        return (bool) $thesis->delete();
    }
}