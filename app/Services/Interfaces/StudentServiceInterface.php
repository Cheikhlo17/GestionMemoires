<?php

namespace App\Services\Interfaces;

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;

    public function find(int $id): Student;

    public function create(array $data): Student;

    public function update(Student $student, array $data): Student;

    public function delete(Student $student): void;
}