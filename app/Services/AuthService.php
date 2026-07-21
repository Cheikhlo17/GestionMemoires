<?php

namespace App\Services;

use App\Exceptions\AccountInactiveException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    public function __construct(protected UserRepositoryInterface $userRepository)
    {
    }

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $studentRole = Role::where('slug', 'student')->firstOrFail();

            $user = $this->userRepository->create([
                'role_id' => $data['role_id'] ?? $studentRole->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            event(new Registered($user));

            $token = $user->createToken('auth_token')->plainTextToken;

            return ['user' => $user->load('role'), 'token' => $token];
        });
    }

    public function login(array $credentials): array
    {
        $user = $this->userRepository->findByEmail($credentials['email']);

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw new InvalidCredentialsException();
        }

        if (!$user->is_active) {
            throw new AccountInactiveException();
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->update(['last_login_at' => now()]);

        return ['user' => $user->load('role'), 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function forgotPassword(string $email): void
    {
        $status = Password::sendResetLink(
            ['email' => $email],
            function (User $user, string $token) {
                $user->notify(new ResetPasswordNotification($token));
            }
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    public function resetPassword(array $data): void
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();

        $user->tokens()->where('id', '!=', optional($user->currentAccessToken())->id)->delete();
    }

    public function updateProfile(User $user, array $data): User
    {
        return $this->userRepository->update($user, $data);
    }
}