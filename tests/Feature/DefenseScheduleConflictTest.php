<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DefenseRoom;
use App\Models\DefenseSchedule;
use App\Models\Department;
use App\Models\JuryMember;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\Thesis;
use App\Models\User;
use App\Repositories\Interfaces\DefenseScheduleRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefenseScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_and_jury_overlap_detection_works_without_database_date_arithmetic(): void
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => 'administrator']);
        $admin = $this->createUser($role, 'admin');
        $juryRole = Role::create(['name' => 'Jury Member', 'slug' => 'jury-member']);
        $juryUser = $this->createUser($juryRole, 'jury');

        $department = Department::create([
            'name' => 'Science',
            'code' => 'SCI',
            'is_active' => true,
        ]);
        $program = Program::create([
            'department_id' => $department->id,
            'name' => 'Science Program',
            'code' => 'SCI-P',
            'degree_level' => 'master',
            'is_active' => true,
        ]);
        $academicYear = AcademicYear::create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'is_current' => true,
        ]);
        $studentUser = $this->createUser(
            Role::create(['name' => 'Student', 'slug' => 'student']),
            'student'
        );
        $student = Student::create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'student_number' => 'STU-001',
            'enrollment_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $thesis = Thesis::create([
            'student_id' => $student->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'title' => 'Scheduled thesis',
        ]);
        $room = DefenseRoom::create([
            'name' => 'Room A',
            'building' => 'Main building',
            'capacity' => 20,
            'is_active' => true,
        ]);
        $juryMember = JuryMember::create([
            'user_id' => $juryUser->id,
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $schedule = DefenseSchedule::create([
            'thesis_id' => $thesis->id,
            'defense_room_id' => $room->id,
            'scheduled_at' => '2026-11-10 10:00:00',
            'duration_minutes' => 60,
            'status' => DefenseSchedule::STATUS_SCHEDULED,
            'created_by' => $admin->id,
        ]);
        $schedule->juryMembers()->attach($juryMember->id, ['role' => 'examiner']);

        $repository = app(DefenseScheduleRepositoryInterface::class);
        $overlapStart = CarbonImmutable::parse('2026-11-10 10:30:00');
        $overlapEnd = CarbonImmutable::parse('2026-11-10 11:30:00');

        $this->assertTrue($repository->hasRoomConflict($room->id, $overlapStart, $overlapEnd));
        $this->assertTrue($repository->hasJuryConflict([$juryMember->id], $overlapStart, $overlapEnd));

        $this->assertFalse($repository->hasRoomConflict(
            $room->id,
            CarbonImmutable::parse('2026-11-10 11:00:00'),
            CarbonImmutable::parse('2026-11-10 12:00:00')
        ));
    }

    private function createUser(Role $role, string $suffix): User
    {
        return User::create([
            'role_id' => $role->id,
            'first_name' => ucfirst($suffix),
            'last_name' => 'User',
            'email' => "{$suffix}@example.test",
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
