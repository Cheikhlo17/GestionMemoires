<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\Thesis;
use App\Models\ThesisSubject;
use App\Models\User;
use App\Services\Interfaces\ThesisSubjectServiceInterface;
use App\Services\Interfaces\ThesisServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ThesisSubjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_proposal_keeps_its_decision_history(): void
    {
        [$studentUser, $headOfDepartment, $department] = $this->createActors();
        $subjects = app(ThesisSubjectServiceInterface::class);
        $subject = $subjects->createProposal([
            'title' => 'Water quality study',
            'problem_statement' => 'Water quality needs measurement.',
            'objectives' => 'Measure local water quality.',
            'keywords' => ['water', 'quality'],
        ], $studentUser);

        $subject = $subjects->submit($subject, $studentUser);
        $subject = $subjects->review($subject, ThesisSubject::STATUS_ACCEPTED, $headOfDepartment);

        $this->assertSame(ThesisSubject::STATUS_ACCEPTED, $subject->status);
        $this->assertSame($department->id, $subject->department_id);
        $this->assertSame([
            ThesisSubject::STATUS_DRAFT,
            ThesisSubject::STATUS_SUBMITTED,
            ThesisSubject::STATUS_ACCEPTED,
        ], $subject->history->pluck('to_status')->all());
        $this->assertSame($headOfDepartment->id, $subject->history->last()->changed_by);
    }

    public function test_a_student_cannot_decide_their_own_proposal(): void
    {
        [$studentUser] = $this->createActors();
        $subject = ThesisSubject::create([
            'student_id' => $studentUser->studentProfile->id,
            'department_id' => $studentUser->studentProfile->department_id,
            'created_by' => $studentUser->id,
            'title' => 'Student proposal',
            'status' => ThesisSubject::STATUS_SUBMITTED,
        ]);

        try {
            app(ThesisSubjectServiceInterface::class)
                ->review($subject, ThesisSubject::STATUS_ACCEPTED, $studentUser);
            $this->fail('A student was allowed to decide their own subject proposal.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('thesis_subject_histories', [
                'thesis_subject_id' => $subject->id,
                'to_status' => ThesisSubject::STATUS_ACCEPTED,
            ]);
        }
    }

    public function test_a_thesis_cannot_be_submitted_without_an_accepted_subject(): void
    {
        [$studentUser, , $department] = $this->createActors();
        $student = $studentUser->studentProfile;
        $thesis = Thesis::create([
            'student_id' => $student->id,
            'department_id' => $department->id,
            'program_id' => $student->program_id,
            'academic_year_id' => $student->academic_year_id,
            'title' => 'Unapproved topic',
            'status' => Thesis::STATUS_DRAFT,
        ]);

        try {
            app(ThesisServiceInterface::class)->submit($thesis, $studentUser);
            $this->fail('A thesis without an accepted subject was submitted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('thesis_subject_id', $exception->errors());
            $this->assertDatabaseHas('theses', [
                'id' => $thesis->id,
                'status' => Thesis::STATUS_DRAFT,
            ]);
        }
    }

    private function createActors(): array
    {
        $studentRole = Role::create(['name' => 'Student', 'slug' => 'student']);
        $headRole = Role::create(['name' => 'Head of Department', 'slug' => 'head-of-department']);
        $studentUser = User::create([
            'role_id' => $studentRole->id,
            'first_name' => 'Ada',
            'last_name' => 'Student',
            'email' => 'student@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $headUser = User::create([
            'role_id' => $headRole->id,
            'first_name' => 'Grace',
            'last_name' => 'Head',
            'email' => 'head@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $department = Department::create([
            'name' => 'Science',
            'code' => 'SCI',
            'head_of_department_id' => $headUser->id,
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
        $student = Student::create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'student_number' => 'STU-001',
            'enrollment_date' => '2026-09-01',
            'status' => 'active',
        ]);

        $studentUser->setRelation('studentProfile', $student);

        return [$studentUser, $headUser, $department];
    }
}
