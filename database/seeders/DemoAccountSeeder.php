<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\JuryMember;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::where('code', 'CS')->first();
        $program = Program::where('code', 'CS-BSC')->first();
        $academicYear = AcademicYear::where('is_current', true)->first();

        // Supervisor
        $supervisorRole = Role::where('slug', 'supervisor')->firstOrFail();
        $supervisorUser = User::updateOrCreate(
            ['email' => 'supervisor@university.edu'],
            [
                'role_id' => $supervisorRole->id,
                'first_name' => 'Amadou',
                'last_name' => 'Diallo',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $supervisor = Supervisor::updateOrCreate(
            ['user_id' => $supervisorUser->id],
            [
                'department_id' => $department->id,
                'title' => 'Dr.',
                'specialization' => 'Software Engineering',
                'max_students' => 5,
                'is_active' => true,
            ]
        );

        // Head of Department
        $hodRole = Role::where('slug', 'head-of-department')->firstOrFail();
        User::updateOrCreate(
            ['email' => 'hod@university.edu'],
            [
                'role_id' => $hodRole->id,
                'first_name' => 'Fatou',
                'last_name' => 'Sow',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Jury Member
        $juryRole = Role::where('slug', 'jury-member')->firstOrFail();
        $juryUser = User::updateOrCreate(
            ['email' => 'jury@university.edu'],
            [
                'role_id' => $juryRole->id,
                'first_name' => 'Moussa',
                'last_name' => 'Ba',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        JuryMember::updateOrCreate(
            ['user_id' => $juryUser->id],
            [
                'department_id' => $department->id,
                'specialization' => 'Databases',
                'is_active' => true,
            ]
        );

        // A second jury member + third for full panels
        foreach ([['Aissatou', 'Ndiaye'], ['Ibrahima', 'Fall']] as [$first, $last]) {
            $u = User::updateOrCreate(
                ['email' => strtolower($first) . '.jury@university.edu'],
                [
                    'role_id' => $juryRole->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'password' => Hash::make('Password123!'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            JuryMember::updateOrCreate(
                ['user_id' => $u->id],
                ['department_id' => $department->id, 'specialization' => 'General', 'is_active' => true]
            );
        }

        // Student
        $studentRole = Role::where('slug', 'student')->firstOrFail();
        $studentUser = User::updateOrCreate(
            ['email' => 'student@university.edu'],
            [
                'role_id' => $studentRole->id,
                'first_name' => 'Cheikh',
                'last_name' => 'Diop',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        Student::updateOrCreate(
            ['user_id' => $studentUser->id],
            [
                'department_id' => $department->id,
                'program_id' => $program->id,
                'academic_year_id' => $academicYear->id,
                'student_number' => 'STU-000001',
                'enrollment_date' => now()->subYears(2),
                'status' => 'active',
            ]
        );
    }
}