<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\PreDefenseSession;
use App\Models\User;

class PreDefenseSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, [
            'administrator',
            'head-of-department',
            'supervisor',
            'student',
        ], true);
    }

    public function view(User $user, PreDefenseSession $session): bool
    {
        $role = $user->role?->slug;

        return $role === 'administrator'
            || $session->responsible_user_id === $user->id
            || ($role === 'head-of-department'
                && Department::whereKey($session->department_id)->where('head_of_department_id', $user->id)->exists())
            || ($role === 'student'
                && $session->passages()->whereHas('student', fn ($students) => $students->where('user_id', $user->id))->exists())
            || ($role === 'supervisor'
                && $session->passages()->whereHas('thesis.supervisor', fn ($supervisors) => $supervisors->where('user_id', $user->id))->exists());
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department', 'supervisor'], true);
    }

    public function manage(User $user, PreDefenseSession $session): bool
    {
        return $user->role?->slug === 'administrator'
            || $session->responsible_user_id === $user->id
            || ($user->role?->slug === 'head-of-department'
                && Department::whereKey($session->department_id)->where('head_of_department_id', $user->id)->exists());
    }
}
