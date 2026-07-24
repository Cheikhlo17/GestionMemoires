<?php

namespace App\Repositories\Interfaces;

use App\Models\Thesis;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ThesisRepositoryInterface
{
    public function paginate(int $perPage, array $filters): LengthAwarePaginator;

    public function find(int $id): ?Thesis;

    public function findByStudentId(int $studentId): ?Thesis;

    public function create(array $data): Thesis;

    public function update(Thesis $thesis, array $data): Thesis;

    public function delete(Thesis $thesis): bool;
}