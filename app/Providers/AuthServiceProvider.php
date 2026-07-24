<?php

namespace App\Providers;

use App\Models\Student;
use App\Models\Thesis;
use App\Models\User;
use App\Policies\StudentPolicy;
use App\Policies\ThesisPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Student::class => StudentPolicy::class,
        Thesis::class => ThesisPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}