<?php

namespace App\Policies;

use App\Models\Thesis;
use App\Models\ThesisCorrection;
use App\Models\User;

class ThesisCorrectionPolicy
{
    public function view(User $user, ThesisCorrection $correction): bool
    {
        return $user->can('view', $correction->thesis);
    }

    public function createCorrection(User $user, Thesis $thesis): bool
    {
        return $user->can('createCorrection', $thesis);
    }

    public function updateStatus(User $user, ThesisCorrection $correction, string $status): bool
    {
        if ($status === ThesisCorrection::STATUS_CORRECTED) {
            return $user->role?->slug === 'student'
                && (int) $user->id === (int) $correction->thesis->student->user_id;
        }

        return in_array($status, [ThesisCorrection::STATUS_TO_CORRECT, ThesisCorrection::STATUS_VALIDATED], true)
            && $this->createCorrection($user, $correction->thesis);
    }
}
