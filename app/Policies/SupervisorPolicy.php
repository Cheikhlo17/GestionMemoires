<?php

namespace App\Policies;

use App\Models\Supervisor;
use App\Models\User;
use App\Support\SupervisorVisibility;

class SupervisorPolicy
{
    public function __construct(private SupervisorVisibility $visibility)
    {
    }

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
        return $this->viewAny($user) && $this->visibility->allows($user, $supervisor);
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
