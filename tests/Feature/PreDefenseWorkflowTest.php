<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\PreDefenseSession;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\Thesis;
use App\Models\User;
use App\Services\Interfaces\PreDefenseServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreDefenseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_passage_conflicts_are_rejected_and_student_roster_is_private(): void
    {
        [$head, $department, $program, $academicYear, $students, $theses] = $this->createActors();
        $service = app(PreDefenseServiceInterface::class);
        $session = $service->createSession($this->sessionData($department, $head, 'Room A', '2026-11-05 09:00:00'), $head);
        $firstPassage = $service->addPassage($session, [
            'thesis_id' => $theses[0]->id,
            'position' => 1,
            'scheduled_at' => '2026-11-05 09:00:00',
            'duration_minutes' => 15,
        ], $head);
        $service->addPassage($session, [
            'thesis_id' => $theses[1]->id,
            'position' => 2,
            'scheduled_at' => '2026-11-05 09:30:00',
            'duration_minutes' => 15,
        ], $head);

        $secondSession = $service->createSession(
            $this->sessionData($department, $head, 'Room B', '2026-11-05 09:10:00'),
            $head
        );

        try {
            $service->addPassage($secondSession, [
                'thesis_id' => $theses[0]->id,
                'position' => 1,
                'scheduled_at' => '2026-11-05 09:10:00',
                'duration_minutes' => 15,
            ], $head);
            $this->fail('An overlapping pre-defense passage was allowed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scheduled_at', $exception->errors());
        }

        Sanctum::actingAs($students[0]);
        $this->getJson("/api/pre-defense-sessions/{$session->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.passages')
            ->assertJsonPath('data.passages.0.id', $firstPassage->id)
            ->assertJsonPath('data.passages.0.student.student_number', 'PRE-001')
            ->assertJsonMissing(['student_number' => 'PRE-002']);
    }

    private function createActors(): array
    {
        $headRole = Role::create(['name' => 'Head of Department', 'slug' => 'head-of-department']);
        $studentRole = Role::create(['name' => 'Student', 'slug' => 'student']);
        $head = User::create([
            'role_id' => $headRole->id,
            'first_name' => 'Casey',
            'last_name' => 'Head',
            'email' => 'predef-head@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $department = Department::create([
            'name' => 'Science',
            'code' => 'SCI',
            'head_of_department_id' => $head->id,
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

        $students = [];
        $theses = [];
        for ($sequence = 1; $sequence <= 2; $sequence++) {
            $studentUser = User::create([
                'role_id' => $studentRole->id,
                'first_name' => "Student{$sequence}",
                'last_name' => 'Test',
                'email' => "predef-student-{$sequence}@example.test",
                'password' => 'password',
                'is_active' => true,
            ]);
            $student = Student::create([
                'user_id' => $studentUser->id,
                'department_id' => $department->id,
                'program_id' => $program->id,
                'academic_year_id' => $academicYear->id,
                'student_number' => "PRE-00{$sequence}",
                'enrollment_date' => '2026-09-01',
                'status' => 'active',
            ]);
            $thesis = Thesis::create([
                'student_id' => $student->id,
                'department_id' => $department->id,
                'program_id' => $program->id,
                'academic_year_id' => $academicYear->id,
                'title' => "Pre-defense thesis {$sequence}",
                'status' => Thesis::STATUS_DRAFT,
            ]);
            $students[] = $studentUser;
            $theses[] = $thesis;
        }

        return [$head, $department, $program, $academicYear, $students, $theses];
    }

    private function sessionData(Department $department, User $head, string $location, string $scheduledAt): array
    {
        return [
            'department_id' => $department->id,
            'responsible_user_id' => $head->id,
            'location' => $location,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 120,
        ];
    }
}
