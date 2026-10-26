<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\DefenseSchedule;
use App\Models\User;

class DefenseSchedulePolicy
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

    public function view(User $user, DefenseSchedule $schedule): bool
    {
        if ($user->role?->slug === 'administrator') {
            return true;
        }

        if ($this->isDepartmentHeadFor($user, $schedule)) {
            return true;
        }

        return $schedule->thesis->student->user_id === $user->id
            || $schedule->thesis->supervisor?->user_id === $user->id
            || $schedule->juryMembers()
                ->where('jury_members.user_id', $user->id)
                ->where('jury_members.is_active', true)
                ->exists();
    }

    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['administrator', 'head-of-department'], true);
    }

    public function update(User $user, DefenseSchedule $schedule): bool
    {
        return $user->role?->slug === 'administrator' || $this->isDepartmentHeadFor($user, $schedule);
    }

    public function assignJury(User $user, DefenseSchedule $schedule): bool
    {
        return $user->role?->slug === 'administrator' || $this->isDepartmentHeadFor($user, $schedule);
    }

    public function publish(User $user, DefenseSchedule $schedule): bool
    {
        return $user->role?->slug === 'administrator' || $this->isDepartmentHeadFor($user, $schedule);
    }

    public function submitEvaluation(User $user, DefenseSchedule $schedule): bool
    {
        return $user->role?->slug === 'jury-member'
            && $schedule->juryMembers()
                ->where('jury_members.user_id', $user->id)
                ->where('jury_members.is_active', true)
                ->exists();
    }

    public function viewEvaluations(User $user, DefenseSchedule $schedule): bool
    {
        if ($user->role?->slug === 'administrator' || $this->isDepartmentHeadFor($user, $schedule)) {
            return true;
        }

        return $user->role?->slug === 'jury-member'
            && $schedule->juryMembers()
                ->where('jury_members.user_id', $user->id)
                ->where('jury_members.is_active', true)
                ->exists();
    }

    public function recordResult(User $user, DefenseSchedule $schedule): bool
    {
        return $user->role?->slug === 'administrator' || $this->isDepartmentHeadFor($user, $schedule);
    }

    public function generateReport(User $user, DefenseSchedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }

    private function isDepartmentHeadFor(User $user, DefenseSchedule $schedule): bool
    {
        return $user->role?->slug === 'head-of-department'
            && Department::whereKey($schedule->thesis->department_id)
                ->where('head_of_department_id', $user->id)
                ->exists();
    }
}