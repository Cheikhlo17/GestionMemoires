<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Thesis;
use App\Models\User;

class ThesisPolicy
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

    public function view(User $user, Thesis $thesis): bool
    {
        return $this->isParticipant($user, $thesis)
            || $user->role?->slug === 'administrator'
            || $this->isDepartmentHeadOf($user, $thesis);
    }

    public function create(User $user): bool
    {
        return $user->role?->slug === 'student';
    }

    public function update(User $user, Thesis $thesis): bool
    {
        return $user->id === $thesis->student->user_id || $user->role?->slug === 'administrator';
    }

    public function delete(User $user, Thesis $thesis): bool
    {
        return $user->role?->slug === 'administrator';
    }

    public function uploadVersion(User $user, Thesis $thesis): bool
    {
        return $user->id === $thesis->student->user_id;
    }

    public function comment(User $user, Thesis $thesis): bool
    {
        return $this->isParticipant($user, $thesis)
            || $user->role?->slug === 'administrator'
            || $this->isDepartmentHeadOf($user, $thesis);
    }

    public function createCorrection(User $user, Thesis $thesis): bool
    {
        if ($user->role?->slug === 'administrator') {
            return true;
        }

        if ($user->role?->slug === 'supervisor') {
            return (int) $thesis->supervisor?->user_id === (int) $user->id;
        }

        return $this->isDepartmentHeadOf($user, $thesis);
    }

    public function createMeeting(User $user, Thesis $thesis): bool
    {
        $role = $user->role?->slug;

        return ($role === 'student' && (int) $thesis->student?->user_id === (int) $user->id)
            || ($role === 'supervisor' && (int) $thesis->supervisor?->user_id === (int) $user->id);
    }

    public function assignSupervisor(User $user, Thesis $thesis): bool
    {
        return $user->role?->slug === 'administrator' || $this->isDepartmentHeadOf($user, $thesis);
    }

    public function changeStatus(User $user, Thesis $thesis): bool
    {
        $role = $user->role?->slug;

        if ($role === 'administrator') {
            return true;
        }

        if ($role === 'supervisor') {
            return $thesis->supervisor?->user_id === $user->id;
        }

        if ($role === 'head-of-department') {
            return $this->isDepartmentHeadOf($user, $thesis);
        }

        return false;
    }

    private function isParticipant(User $user, Thesis $thesis): bool
    {
        return $user->id === $thesis->student->user_id
            || $user->id === $thesis->supervisor?->user_id;
    }

    private function isDepartmentHeadOf(User $user, Thesis $thesis): bool
    {
        return $user->role?->slug === 'head-of-department'
            && Department::whereKey($thesis->department_id)
                ->where('head_of_department_id', $user->id)
                ->exists();
    }
}