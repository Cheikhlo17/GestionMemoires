<?php

namespace App\Repositories\Interfaces;

use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SupervisorRepositoryInterface
{
    public function paginate(int $perPage, array $filters, User $viewer): LengthAwarePaginator;

    public function find(int $id): ?Supervisor;

    public function findByUserId(int $userId): ?Supervisor;

    public function create(array $data): Supervisor;

    public function update(Supervisor $supervisor, array $data): Supervisor;

    public function delete(Supervisor $supervisor): bool;

    public function countAssignedTheses(Supervisor $supervisor): int;
}
