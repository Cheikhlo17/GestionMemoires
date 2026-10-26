<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DefenseRoom;
use App\Models\DefenseSchedule;
use App\Models\Department;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicDefenseCalendarTest extends TestCase
{
    use RefreshDatabase;

    private int $studentRoleId;

    public function test_public_calendar_only_returns_published_scheduled_defenses_without_private_details(): void
    {
        [$admin, $department, $program, $academicYear] = $this->createBaseRecords();
        $room = DefenseRoom::create([
            'name' => 'Room A',
            'building' => 'Main building',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $public = $this->createSchedule($admin, $department, $program, $academicYear, $room, 'scheduled', true, 1);
        $this->createSchedule($admin, $department, $program, $academicYear, $room, 'scheduled', false, 2);
        $this->createSchedule($admin, $department, $program, $academicYear, $room, 'cancelled', true, 3);

        $response = $this->getJson('/api/defense-calendar?from=2026-11-01&to=2026-11-30');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $public->id)
            ->assertJsonPath('data.0.thesis.title', 'Public test thesis 1')
            ->assertJsonPath('data.0.room.name', 'Room A')
            ->assertJsonMissingPath('data.0.thesis.student_name')
            ->assertJsonMissingPath('data.0.thesis.supervisor_name')
            ->assertJsonMissingPath('data.0.result');
    }

    public function test_a_final_report_requires_a_recorded_result(): void
    {
        [$admin, $department, $program, $academicYear] = $this->createBaseRecords();
        $room = DefenseRoom::create([
            'name' => 'Room B',
            'building' => 'Main building',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $schedule = $this->createSchedule(
            $admin,
            $department,
            $program,
            $academicYear,
            $room,
            'scheduled',
            false,
            1
        );

        Sanctum::actingAs($admin);
        $this->getJson("/api/defense-schedules/{$schedule->id}/report")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('result');
    }

    private function createBaseRecords(): array
    {
        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'administrator']);
        $studentRole = Role::create(['name' => 'Student', 'slug' => 'student']);
        $admin = User::create([
            'role_id' => $adminRole->id,
            'first_name' => 'Alex',
            'last_name' => 'Admin',
            'email' => 'admin-calendar@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $department = Department::create([
            'name' => 'Science',
            'code' => 'SCI',
            'is_active' => true,
        ]);
        $program = Program::create([
            'department_id' => $department->id,
            'name' => 'Science Program',
            'code' => 'SCI-1',
            'degree_level' => 'master',
            'is_active' => true,
        ]);
        $academicYear = AcademicYear::create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'is_current' => true,
        ]);

        $this->studentRoleId = $studentRole->id;

        return [$admin, $department, $program, $academicYear];
    }

    private function createSchedule(
        User $admin,
        Department $department,
        Program $program,
        AcademicYear $academicYear,
        DefenseRoom $room,
        string $status,
        bool $isPublic,
        int $sequence
    ): DefenseSchedule {
        $studentUser = User::create([
            'role_id' => $this->studentRoleId,
            'first_name' => 'Student',
            'last_name' => (string) $sequence,
            'email' => "student-{$sequence}-calendar@example.test",
            'password' => 'password',
            'is_active' => true,
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'student_number' => "CAL-{$sequence}",
            'enrollment_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $thesis = Thesis::create([
            'student_id' => $student->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'title' => "Public test thesis {$sequence}",
            'status' => Thesis::STATUS_APPROVED,
        ]);

        return DefenseSchedule::create([
            'thesis_id' => $thesis->id,
            'defense_room_id' => $room->id,
            'scheduled_at' => "2026-11-0{$sequence} 10:00:00",
            'duration_minutes' => 60,
            'status' => $status,
            'is_public' => $isPublic,
            'created_by' => $admin->id,
        ]);
    }
}
