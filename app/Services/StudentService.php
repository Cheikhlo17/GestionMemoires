<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use App\Services\Interfaces\StudentServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StudentService implements StudentServiceInterface
{
    public function __construct(protected StudentRepositoryInterface $studentRepository)
    {
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->studentRepository->paginate($filters['per_page'] ?? 15, $filters);
    }

    public function find(int $id): Student
    {
        $student = $this->studentRepository->find($id);

        if (!$student) {
            throw new NotFoundHttpException('Student not found.');
        }

        return $student;
    }

    public function create(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            $studentRole = Role::where('slug', 'student')->firstOrFail();

            $user = User::create([
                'role_id' => $studentRole->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password'] ?? Str::random(12)),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            return $this->studentRepository->create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'],
                'program_id' => $data['program_id'],
                'academic_year_id' => $data['academic_year_id'],
                'student_number' => $data['student_number'],
                'enrollment_date' => $data['enrollment_date'],
                'status' => $data['status'] ?? 'active',
            ]);
        });
    }

    public function update(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data) {
            $userData = array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ], fn ($v) => $v !== null);

            if (!empty($userData)) {
                $student->user()->update($userData);
            }

            $studentData = array_filter([
                'department_id' => $data['department_id'] ?? null,
                'program_id' => $data['program_id'] ?? null,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'student_number' => $data['student_number'] ?? null,
                'enrollment_date' => $data['enrollment_date'] ?? null,
                'status' => $data['status'] ?? null,
            ], fn ($v) => $v !== null);

            return $this->studentRepository->update($student, $studentData);
        });
    }

    public function delete(Student $student): void
    {
        DB::transaction(function () use ($student) {
            $this->studentRepository->delete($student);
            $student->user?->update(['is_active' => false]);
        });
    }
}