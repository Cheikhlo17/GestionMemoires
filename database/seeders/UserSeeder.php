<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'administrator')->first();

        User::updateOrCreate(
            ['email' => 'admin@university.edu'],
            [
                'role_id' => $adminRole->id,
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
    }
}