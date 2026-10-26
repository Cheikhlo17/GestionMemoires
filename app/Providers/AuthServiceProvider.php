<?php

namespace App\Providers;

use App\Models\DefenseSchedule;
use App\Models\Department;
use App\Models\PreDefenseSession;
use App\Models\Student;
use App\Models\SupervisionMeeting;
use App\Models\Supervisor;
use App\Models\Thesis;
use App\Models\ThesisArchive;
use App\Models\ThesisCorrection;
use App\Models\ThesisSubject;
use App\Models\User;
use App\Policies\DefenseSchedulePolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\PreDefenseSessionPolicy;
use App\Policies\StudentPolicy;
use App\Policies\SupervisionMeetingPolicy;
use App\Policies\SupervisorPolicy;
use App\Policies\ThesisArchivePolicy;
use App\Policies\ThesisCorrectionPolicy;
use App\Policies\ThesisPolicy;
use App\Policies\ThesisSubjectPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Student::class => StudentPolicy::class,
        SupervisionMeeting::class => SupervisionMeetingPolicy::class,
        Supervisor::class => SupervisorPolicy::class,
        Thesis::class => ThesisPolicy::class,
        ThesisSubject::class => ThesisSubjectPolicy::class,
        DefenseSchedule::class => DefenseSchedulePolicy::class,
        PreDefenseSession::class => PreDefenseSessionPolicy::class,
        Department::class => DepartmentPolicy::class,
        ThesisArchive::class => ThesisArchivePolicy::class,
        ThesisCorrection::class => ThesisCorrectionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}