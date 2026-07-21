<?php

namespace App\Policies;

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
        return in_array($user->role?->slug, ['administrator', 'supervisor', 'head-of-department'], true)
            || $user->id === $student->user_id;
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