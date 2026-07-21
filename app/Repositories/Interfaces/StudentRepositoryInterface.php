<?php

namespace App\Repositories\Interfaces;

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentRepositoryInterface
{
    public function paginate(int $perPage, array $filters): LengthAwarePaginator;

    public function find(int $id): ?Student;

    public function create(array $data): Student;

    public function update(Student $student, array $data): Student;

    public function delete(Student $student): bool;

    public function studentNumberExists(string $studentNumber, ?int $exceptId = null): bool;
}