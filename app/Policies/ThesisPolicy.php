<?php

namespace App\Policies;

use App\Models\Thesis;
use App\Models\User;

class ThesisPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Thesis $thesis): bool
    {
        return $this->isParticipant($user, $thesis) || $this->isPrivileged($user);
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
        return $this->isParticipant($user, $thesis) || $this->isPrivileged($user);
    }

    public function assignSupervisor(User $user, Thesis $thesis): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
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
            return true;
        }

        return false;
    }

    private function isParticipant(User $user, Thesis $thesis): bool
    {
        return $user->id === $thesis->student->user_id
            || $user->id === $thesis->supervisor?->user_id;
    }

    private function isPrivileged(User $user): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }
}