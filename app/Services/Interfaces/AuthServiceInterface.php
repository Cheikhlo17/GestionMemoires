<?php

namespace App\Services\Interfaces;

use App\Models\User;

interface AuthServiceInterface
{
    public function register(array $data): array;

    public function login(array $credentials): array;

    public function logout(User $user): void;

    public function forgotPassword(string $email): void;

    public function resetPassword(array $data): void;

    public function changePassword(User $user, string $currentPassword, string $newPassword): void;

    public function updateProfile(User $user, array $data): User;
}