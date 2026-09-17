<?php

namespace App\Repositories;

use App\Models\Supervisor;
use App\Models\Thesis;
use App\Repositories\Interfaces\SupervisorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupervisorRepository implements SupervisorRepositoryInterface
{
    public function __construct(protected Supervisor $model)
    {
    }

    public function paginate(int $perPage, array $filters): LengthAwarePaginator
    {
        $query = $this->model->with(['user', 'department']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('specialization', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
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

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['sort_by'])) {
            $direction = $filters['sort_direction'] ?? 'asc';
            $query->orderBy($filters['sort_by'], $direction);
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): ?Supervisor
    {
        return $this->model->with(['user', 'department'])->find($id);
    }

    public function findByUserId(int $userId): ?Supervisor
    {
        return $this->model->with(['user', 'department'])->where('user_id', $userId)->first();
    }

    public function create(array $data): Supervisor
    {
        return $this->model->create($data);
    }

    public function update(Supervisor $supervisor, array $data): Supervisor
    {
        $supervisor->update($data);

        return $supervisor->fresh(['user', 'department']);
    }

    public function delete(Supervisor $supervisor): bool
    {
        return (bool) $supervisor->delete();
    }

    public function countAssignedTheses(Supervisor $supervisor): int
    {
        return Thesis::where('supervisor_id', $supervisor->id)
            ->whereNotIn('status', [Thesis::STATUS_ARCHIVED, Thesis::STATUS_REJECTED])
            ->count();
    }
}