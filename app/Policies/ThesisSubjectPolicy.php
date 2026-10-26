<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Student;
use App\Models\ThesisSubject;
use App\Models\User;

class ThesisSubjectPolicy
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

    public function view(User $user, ThesisSubject $subject): bool
    {
        if ($user->role?->slug === 'administrator') {
            return true;
        }

        if ($user->role?->slug === 'student') {
            $studentId = Student::where('user_id', $user->id)->value('id');

            return (int) $subject->student_id === (int) $studentId
                || ($subject->status === ThesisSubject::STATUS_PUBLISHED
                    && $subject->is_published
                    && $subject->student_id === null
                    && $studentId !== null
                    && (int) $subject->department_id === (int) Student::whereKey($studentId)->value('department_id'));
        }

        if ($user->role?->slug === 'head-of-department') {
            return Department::whereKey($subject->department_id)
                ->where('head_of_department_id', $user->id)
                ->exists();
        }

        return $user->role?->slug === 'supervisor'
            && $subject->thesis()->whereHas('supervisor', fn ($query) => $query->where('user_id', $user->id))->exists();
    }

    public function create(User $user): bool
    {
        return $user->role?->slug === 'student';
    }

    public function update(User $user, ThesisSubject $subject): bool
    {
        return $user->role?->slug === 'student'
            && (int) $subject->student?->user_id === (int) $user->id
            && in_array($subject->status, [ThesisSubject::STATUS_DRAFT, ThesisSubject::STATUS_REJECTED], true);
    }

    public function publish(User $user, int $departmentId): bool
    {
        return $user->role?->slug === 'administrator'
            || ($user->role?->slug === 'head-of-department'
                && Department::whereKey($departmentId)->where('head_of_department_id', $user->id)->exists());
    }

    public function submit(User $user, ThesisSubject $subject): bool
    {
        return $user->role?->slug === 'student'
            && (int) $subject->student?->user_id === (int) $user->id;
    }

    public function select(User $user, ThesisSubject $subject): bool
    {
        return $user->role?->slug === 'student'
            && $subject->student_id === null
            && $subject->status === ThesisSubject::STATUS_PUBLISHED
            && $subject->is_published
            && Student::where('user_id', $user->id)
                ->where('department_id', $subject->department_id)
                ->exists();
    }

    public function review(User $user, ThesisSubject $subject): bool
    {
        return $user->role?->slug === 'administrator'
            || ($user->role?->slug === 'head-of-department'
                && Department::whereKey($subject->department_id)->where('head_of_department_id', $user->id)->exists());
    }
}
