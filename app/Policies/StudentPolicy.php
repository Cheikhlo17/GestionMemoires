<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, ['administrator', 'supervisor', 'head-of-department'], true);
    }

    public function view(User $user, Student $student): bool
    {
        return $user->role?->slug === 'administrator'
            || $user->id === $student->user_id
            || ($user->role?->slug === 'head-of-department'
                && Department::whereKey($student->department_id)->where('head_of_department_id', $user->id)->exists())
            || ($user->role?->slug === 'supervisor'
                && $student->theses()->whereHas('supervisor', fn ($q) => $q->where('user_id', $user->id))->exists());
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-users') || $user->role?->slug === 'administrator';
    }

    public function update(User $user, Student $student): bool
    {
        return $user->role?->slug === 'administrator' || $user->id === $student->user_id;
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->role?->slug === 'administrator';
    }
}