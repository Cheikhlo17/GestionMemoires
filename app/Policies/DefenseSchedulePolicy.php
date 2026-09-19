<?php

namespace App\Policies;

use App\Models\DefenseSchedule;
use App\Models\User;

class DefenseSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DefenseSchedule $schedule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function update(User $user, DefenseSchedule $schedule): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function assignJury(User $user, DefenseSchedule $schedule): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function submitEvaluation(User $user, DefenseSchedule $schedule): bool
    {
        return $user->role?->slug === 'jury-member'
            && $schedule->juryMembers()->where('user_id', $user->id)->exists();
    }

    public function viewEvaluations(User $user, DefenseSchedule $schedule): bool
    {
        if (in_array($user->role?->slug, ['administrator', 'head-of-department'], true)) {
            return true;
        }

        return $user->role?->slug === 'jury-member'
            && $schedule->juryMembers()->where('user_id', $user->id)->exists();
    }

    public function recordResult(User $user, DefenseSchedule $schedule): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function generateReport(User $user, DefenseSchedule $schedule): bool
    {
        return true;
    }
}