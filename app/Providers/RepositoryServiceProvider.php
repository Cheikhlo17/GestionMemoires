<?php

namespace App\Providers;

use App\Repositories\DefenseScheduleRepository;
use App\Repositories\Interfaces\DefenseScheduleRepositoryInterface;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use App\Repositories\Interfaces\SupervisorRepositoryInterface;
use App\Repositories\Interfaces\ThesisRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\StudentRepository;
use App\Repositories\SupervisorRepository;
use App\Repositories\ThesisRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\DefenseScheduleService;
use App\Services\Interfaces\AuthServiceInterface;
use App\Services\Interfaces\DefenseScheduleServiceInterface;
use App\Services\Interfaces\StudentServiceInterface;
use App\Services\Interfaces\SupervisorServiceInterface;
use App\Services\Interfaces\ThesisServiceInterface;
use App\Services\StudentService;
use App\Services\SupervisorService;
use App\Services\ThesisService;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);

        $this->app->bind(StudentRepositoryInterface::class, StudentRepository::class);
        $this->app->bind(StudentServiceInterface::class, StudentService::class);

        $this->app->bind(SupervisorRepositoryInterface::class, SupervisorRepository::class);
        $this->app->bind(SupervisorServiceInterface::class, SupervisorService::class);

        $this->app->bind(ThesisRepositoryInterface::class, ThesisRepository::class);
        $this->app->bind(ThesisServiceInterface::class, ThesisService::class);

        $this->app->bind(DefenseScheduleRepositoryInterface::class, DefenseScheduleRepository::class);
        $this->app->bind(DefenseScheduleServiceInterface::class, DefenseScheduleService::class);
    }

    public function boot(): void
    {
    }
}