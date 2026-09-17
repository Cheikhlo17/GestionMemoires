<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Supervisor;
use App\Models\User;
use App\Repositories\Interfaces\SupervisorRepositoryInterface;
use App\Services\Interfaces\SupervisorServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupervisorService implements SupervisorServiceInterface
{
    public function __construct(protected SupervisorRepositoryInterface $supervisorRepository)
    {
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->supervisorRepository->paginate($filters['per_page'] ?? 15, $filters);
    }

    public function find(int $id): Supervisor
    {
        $supervisor = $this->supervisorRepository->find($id);

        if (!$supervisor) {
            throw new NotFoundHttpException('Supervisor not found.');
        }

        return $supervisor;
    }

    public function create(array $data): Supervisor
    {
        return DB::transaction(function () use ($data) {
            $supervisorRole = Role::where('slug', 'supervisor')->firstOrFail();

            $user = User::create([
                'role_id' => $supervisorRole->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password'] ?? Str::random(12)),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            return $this->supervisorRepository->create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'],
                'title' => $data['title'] ?? null,
                'specialization' => $data['specialization'] ?? null,
                'max_students' => $data['max_students'] ?? 5,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function update(Supervisor $supervisor, array $data): Supervisor
    {
        return DB::transaction(function () use ($supervisor, $data) {
            $userData = array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ], fn ($value) => $value !== null);

            if (!empty($userData)) {
                $supervisor->user()->update($userData);
            }

            $supervisorData = array_filter([
                'department_id' => $data['department_id'] ?? null,
                'title' => $data['title'] ?? null,
                'specialization' => $data['specialization'] ?? null,
                'max_students' => $data['max_students'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ], fn ($value) => $value !== null);

            return $this->supervisorRepository->update($supervisor, $supervisorData);
        });
    }

    public function delete(Supervisor $supervisor): void
    {
        $assigned = $this->supervisorRepository->countAssignedTheses($supervisor);

        if ($assigned > 0) {
            throw new \RuntimeException(
                "This supervisor still has {$assigned} active thesis assignment(s) and cannot be deleted."
            );
        }

        DB::transaction(function () use ($supervisor) {
            $this->supervisorRepository->delete($supervisor);
            $supervisor->user?->update(['is_active' => false]);
        });
    }

    public function hasCapacity(Supervisor $supervisor): bool
    {
        return $this->supervisorRepository->countAssignedTheses($supervisor) < $supervisor->max_students;
    }
}