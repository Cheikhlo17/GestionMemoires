<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Thesis;
use App\Models\ThesisArchive;
use App\Models\User;

class ThesisArchivePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function view(User $user, ThesisArchive $archive): bool
    {
        return $archive->isPublic()
            || $user->role?->slug === 'administrator'
            || $this->isDepartmentHeadFor($user, $archive->thesis->department_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function archive(User $user, Thesis $thesis): bool
    {
        return $user->role?->slug === 'administrator'
            || $this->isDepartmentHeadFor($user, $thesis->department_id);
    }

    public function update(User $user, ThesisArchive $archive): bool
    {
        return $user->role?->slug === 'administrator'
            || $this->isDepartmentHeadFor($user, $archive->thesis->department_id);
    }

    public function delete(User $user, ThesisArchive $archive): bool
    {
        return $user->role?->slug === 'administrator';
    }

    private function isDepartmentHeadFor(User $user, int $departmentId): bool
    {
        return $user->role?->slug === 'head-of-department'
            && Department::whereKey($departmentId)
                ->where('head_of_department_id', $user->id)
                ->exists();
    }
}