<?php

namespace App\Support;

use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SupervisorVisibility
{
    public function scope(Builder $query, User $viewer): Builder
    {
        return match ($viewer->role?->slug) {
            'administrator' => $query,
            'head-of-department' => $query->whereHas(
                'department',
                fn (Builder $departmentQuery) => $departmentQuery->where('head_of_department_id', $viewer->id)
            ),
            'supervisor' => $query->where('user_id', $viewer->id),
            'student' => $query->whereHas(
                'theses.student',
                fn (Builder $studentQuery) => $studentQuery->where('user_id', $viewer->id)
            ),
            'jury-member' => $query->whereHas(
                'theses.defenseSchedule.juryMembers',
                fn (Builder $juryQuery) => $juryQuery
                    ->where('jury_members.user_id', $viewer->id)
                    ->where('jury_members.is_active', true)
            ),
            default => $query->whereKey(0),
        };
    }

    public function allows(User $viewer, Supervisor $supervisor): bool
    {
        return $this->scope(Supervisor::query(), $viewer)
            ->whereKey($supervisor->id)
            ->exists();
    }
}
