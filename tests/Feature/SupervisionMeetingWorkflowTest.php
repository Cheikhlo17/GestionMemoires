<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\SupervisionMeetingAction;
use App\Models\Supervisor;
use App\Models\Thesis;
use App\Models\User;
use App\Services\Interfaces\SupervisionMeetingServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupervisionMeetingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_meetings_are_private_and_action_items_keep_responsibility(): void
    {
        [$student, , $thesis, $department, $program, $academicYear, $supervisorProfile] = $this->createActors();
        $service = app(SupervisionMeetingServiceInterface::class);
        $meeting = $service->create($thesis, [
            'scheduled_at' => '2026-11-05 10:00:00',
            'mode' => 'online',
            'agenda' => 'Review progress',
        ], $student);
        $action = $service->createAction($meeting, [
            'assigned_to' => $student->id,
            'description' => 'Revise the research plan',
            'due_at' => '2026-11-10',
        ], $student);
        $action = $service->updateAction($meeting, $action, [
            'status' => SupervisionMeetingAction::STATUS_IN_PROGRESS,
        ], $student);

        $otherStudent = $this->createStudent($department, $program, $academicYear, 2);
        $this->assertSame(SupervisionMeetingAction::STATUS_IN_PROGRESS, $action->status);
        $this->assertSame(1, $service->list($student, [])->total());
        $this->assertSame(0, $service->list($otherStudent, [])->total());
        $this->assertSame(3, $meeting->history()->count());

        $otherThesis = $this->createThesis($otherStudent, $department, $program, $academicYear, $supervisorProfile);

        try {
            $service->create($otherThesis, [
                'scheduled_at' => '2026-11-05 10:30:00',
                'mode' => 'online',
            ], $otherStudent);
            $this->fail('An overlapping meeting for the same supervisor was allowed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scheduled_at', $exception->errors());
        }
    }

    private function createActors(): array
    {
        $studentRole = Role::create(['name' => 'Student', 'slug' => 'student']);
        $supervisorRole = Role::create(['name' => 'Supervisor', 'slug' => 'supervisor']);
        $student = User::create([
            'role_id' => $studentRole->id,
            'first_name' => 'Sam',
            'last_name' => 'Student',
            'email' => 'meeting-student-1@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $supervisor = User::create([
            'role_id' => $supervisorRole->id,
            'first_name' => 'Pat',
            'last_name' => 'Supervisor',
            'email' => 'meeting-supervisor@example.test',
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
        $studentProfile = Student::create([
            'user_id' => $student->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'student_number' => 'MEET-1',
            'enrollment_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $supervisorProfile = Supervisor::create([
            'user_id' => $supervisor->id,
            'department_id' => $department->id,
            'max_students' => 5,
            'is_active' => true,
        ]);
        $thesis = $this->createThesis($student, $department, $program, $academicYear, $supervisorProfile, $studentProfile);

        return [$student, $supervisor, $thesis, $department, $program, $academicYear, $supervisorProfile];
    }

    private function createStudent(Department $department, Program $program, AcademicYear $academicYear, int $sequence): User
    {
        $studentRole = Role::where('slug', 'student')->firstOrFail();
        $user = User::create([
            'role_id' => $studentRole->id,
            'first_name' => 'Other',
            'last_name' => 'Student',
            'email' => "meeting-student-{$sequence}@example.test",
            'password' => 'password',
            'is_active' => true,
        ]);
        $profile = Student::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'student_number' => "MEET-{$sequence}",
            'enrollment_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $user->setRelation('studentProfile', $profile);

        return $user;
    }

    private function createThesis(
        User $student,
        Department $department,
        Program $program,
        AcademicYear $academicYear,
        Supervisor $supervisor,
        ?Student $studentProfile = null
    ): Thesis {
        $studentProfile ??= Student::where('user_id', $student->id)->firstOrFail();

        return Thesis::create([
            'student_id' => $studentProfile->id,
            'supervisor_id' => $supervisor->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'title' => "Meeting test {$studentProfile->student_number}",
            'status' => Thesis::STATUS_DRAFT,
        ]);
    }
}
