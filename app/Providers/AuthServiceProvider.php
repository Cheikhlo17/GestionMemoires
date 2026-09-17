<?php

namespace App\Providers;

use App\Models\DefenseSchedule;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Thesis;
use App\Models\User;
use App\Policies\DefenseSchedulePolicy;
use App\Policies\StudentPolicy;
use App\Policies\SupervisorPolicy;
use App\Policies\ThesisPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Student::class => StudentPolicy::class,
        Supervisor::class => SupervisorPolicy::class,
        Thesis::class => ThesisPolicy::class,
        DefenseSchedule::class => DefenseSchedulePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}