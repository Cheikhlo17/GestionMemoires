<?php

namespace App\Services\Interfaces;

use App\Models\Supervisor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SupervisorServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;

    public function find(int $id): Supervisor;

    public function create(array $data): Supervisor;

    public function update(Supervisor $supervisor, array $data): Supervisor;

    public function delete(Supervisor $supervisor): void;

    public function hasCapacity(Supervisor $supervisor): bool;
}