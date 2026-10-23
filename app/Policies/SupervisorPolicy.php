<?php

namespace App\Policies;

use App\Models\Supervisor;
use App\Models\User;

class SupervisorPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->slug, [
            'administrator',
            'head-of-department',
            'supervisor',
            'student',
            'jury-member',
        ], true);
    }

    public function view(User $user, Supervisor $supervisor): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function update(User $user, Supervisor $supervisor): bool
    {
        return $user->role?->slug === 'administrator'
            || $user->id === $supervisor->user_id;
    }

    public function delete(User $user, Supervisor $supervisor): bool
    {
        return $user->role?->slug === 'administrator';
    }
}