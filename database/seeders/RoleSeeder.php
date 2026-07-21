<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Full system access'],
            ['name' => 'Student', 'slug' => 'student', 'description' => 'Thesis author'],
            ['name' => 'Supervisor', 'slug' => 'supervisor', 'description' => 'Thesis supervisor'],
            ['name' => 'Head of Department', 'slug' => 'head-of-department', 'description' => 'Department validation authority'],
            ['name' => 'Jury Member', 'slug' => 'jury-member', 'description' => 'Defense evaluator'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}