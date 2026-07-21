<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Manage Users', 'slug' => 'manage-users', 'module' => 'users'],
            ['name' => 'Manage Departments', 'slug' => 'manage-departments', 'module' => 'departments'],
            ['name' => 'Manage Programs', 'slug' => 'manage-programs', 'module' => 'programs'],
            ['name' => 'Submit Thesis', 'slug' => 'submit-thesis', 'module' => 'theses'],
            ['name' => 'Review Thesis', 'slug' => 'review-thesis', 'module' => 'theses'],
            ['name' => 'Validate Thesis', 'slug' => 'validate-thesis', 'module' => 'theses'],
            ['name' => 'Assign Jury', 'slug' => 'assign-jury', 'module' => 'defense'],
            ['name' => 'Schedule Defense', 'slug' => 'schedule-defense', 'module' => 'defense'],
            ['name' => 'Evaluate Defense', 'slug' => 'evaluate-defense', 'module' => 'defense'],
            ['name' => 'View Audit Logs', 'slug' => 'view-audit-logs', 'module' => 'audit'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $admin = Role::where('slug', 'administrator')->first();
        if ($admin) {
            $admin->permissions()->sync(Permission::pluck('id'));
        }

        $supervisor = Role::where('slug', 'supervisor')->first();
        if ($supervisor) {
            $supervisor->permissions()->sync(
                Permission::whereIn('slug', ['review-thesis'])->pluck('id')
            );
        }

        $hod = Role::where('slug', 'head-of-department')->first();
        if ($hod) {
            $hod->permissions()->sync(
                Permission::whereIn('slug', ['validate-thesis', 'assign-jury'])->pluck('id')
            );
        }

        $student = Role::where('slug', 'student')->first();
        if ($student) {
            $student->permissions()->sync(
                Permission::whereIn('slug', ['submit-thesis'])->pluck('id')
            );
        }

        $jury = Role::where('slug', 'jury-member')->first();
        if ($jury) {
            $jury->permissions()->sync(
                Permission::whereIn('slug', ['evaluate-defense'])->pluck('id')
            );
        }
    }
}