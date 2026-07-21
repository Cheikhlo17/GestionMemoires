<?php

namespace App\Providers;

use App\Repositories\Interfaces\StudentRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\Interfaces\AuthServiceInterface;
use App\Services\Interfaces\StudentServiceInterface;
use App\Services\StudentService;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(StudentRepositoryInterface::class, StudentRepository::class);
        $this->app->bind(StudentServiceInterface::class, StudentService::class);
    }

    public function boot(): void
    {
    }
}