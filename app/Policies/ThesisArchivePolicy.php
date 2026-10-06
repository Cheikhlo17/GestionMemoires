<?php

namespace App\Policies;

use App\Models\ThesisArchive;
use App\Models\User;

class ThesisArchivePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ThesisArchive $archive): bool
    {
        return $archive->isPublic()
            || in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function update(User $user, ThesisArchive $archive): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function delete(User $user, ThesisArchive $archive): bool
    {
        return $user->role?->slug === 'administrator';
    }
}