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
use App\Models\Supervisor;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupervisorVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $departmentHead;

    private User $studentUser;

    private User $supervisorUser;

    private User $juryUser;

    private Supervisor $departmentSupervisor;

    private Supervisor $otherDepartmentSupervisor;

    private JuryMember $juryMember;

    protected function setUp(): void
    {
        parent::setUp();

        $roles = collect(['administrator', 'head-of-department', 'student', 'supervisor', 'jury-member'])
            ->mapWithKeys(fn (string $slug) => [
                $slug => Role::create(['name' => $slug, 'slug' => $slug])->id,
            ]);

        $this->administrator = $this->createUser($roles['administrator'], 'admin');
        $this->departmentHead = $this->createUser($roles['head-of-department'], 'head');
        $this->studentUser = $this->createUser($roles['student'], 'student');
        $this->supervisorUser = $this->createUser($roles['supervisor'], 'supervisor');
        $this->juryUser = $this->createUser($roles['jury-member'], 'jury');

        $department = Department::create([
            'name' => 'Science',
            'code' => 'SCI',
            'head_of_department_id' => $this->departmentHead->id,
            'is_active' => true,
        ]);
        $otherDepartment = Department::create([
            'name' => 'Arts',
            'code' => 'ART',
            'is_active' => true,
        ]);

        $this->departmentSupervisor = $this->createSupervisor($this->supervisorUser, $department);
        $otherSupervisorUser = $this->createUser($roles['supervisor'], 'other-supervisor');
        $this->otherDepartmentSupervisor = $this->createSupervisor($otherSupervisorUser, $otherDepartment);

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
        $student = Student::create([
            'user_id' => $this->studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'student_number' => 'STU-001',
            'enrollment_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $thesis = Thesis::create([
            'student_id' => $student->id,
            'supervisor_id' => $this->departmentSupervisor->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'title' => 'Scoped thesis',
        ]);
        $room = DefenseRoom::create([
            'name' => 'Room A',
            'building' => 'Main building',
            'capacity' => 20,
            'is_active' => true,
        ]);
        $schedule = DefenseSchedule::create([
            'thesis_id' => $thesis->id,
            'defense_room_id' => $room->id,
            'scheduled_at' => '2026-11-10 10:00:00',
            'duration_minutes' => 60,
            'status' => DefenseSchedule::STATUS_SCHEDULED,
            'created_by' => $this->administrator->id,
        ]);
        $this->juryMember = JuryMember::create([
            'user_id' => $this->juryUser->id,
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $schedule->juryMembers()->attach($this->juryMember->id, ['role' => 'examiner']);
    }

    public function test_administrator_and_department_head_only_see_permitted_supervisor_profiles(): void
    {
        Sanctum::actingAs($this->administrator);
        $this->getJson('/api/supervisors')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        Sanctum::actingAs($this->departmentHead);
        $this->getJson('/api/supervisors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->departmentSupervisor->id);

        $this->getJson('/api/supervisors?department_id='.$this->otherDepartmentSupervisor->department_id)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/supervisors/{$this->otherDepartmentSupervisor->id}")
            ->assertForbidden();
    }

    public function test_students_supervisors_and_jury_members_only_see_profiles_related_to_them(): void
    {
        Sanctum::actingAs($this->studentUser);
        $this->getJson('/api/supervisors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->departmentSupervisor->id);

        $this->getJson("/api/supervisors/{$this->otherDepartmentSupervisor->id}")
            ->assertForbidden();

        Sanctum::actingAs($this->supervisorUser);
        $this->getJson('/api/supervisors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->departmentSupervisor->id);

        Sanctum::actingAs($this->juryUser);
        $this->getJson('/api/supervisors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->departmentSupervisor->id);
    }

    private function createUser(int $roleId, string $suffix): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => ucfirst($suffix),
            'last_name' => 'User',
            'email' => "{$suffix}@example.test",
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    private function createSupervisor(User $user, Department $department): Supervisor
    {
        return Supervisor::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'title' => 'Lecturer',
            'specialization' => 'Research',
            'max_students' => 5,
            'is_active' => true,
        ]);
    }
}
