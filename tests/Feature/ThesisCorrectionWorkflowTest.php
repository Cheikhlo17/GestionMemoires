<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Thesis;
use App\Models\ThesisCorrection;
use App\Models\ThesisVersion;
use App\Models\User;
use App\Services\Interfaces\ThesisCorrectionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThesisCorrectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrections_remain_bound_to_a_version_and_keep_status_history(): void
    {
        [$student, $supervisor, $thesis] = $this->createThesis();
        $version = ThesisVersion::create([
            'thesis_id' => $thesis->id,
            'version_number' => 1,
            'file_path' => 'theses/1/version-1.pdf',
            'file_name' => 'version-1.pdf',
            'file_size' => 100,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $student->id,
        ]);
        $service = app(ThesisCorrectionServiceInterface::class);
        $correction = $service->create($thesis, [
            'thesis_version_id' => $version->id,
            'page_number' => 4,
            'section' => 'Methodology',
            'remark' => 'Clarify the sampling process.',
            'priority' => ThesisCorrection::PRIORITY_HIGH,
        ], $supervisor);

        $correction = $service->updateStatus(
            $correction,
            ThesisCorrection::STATUS_CORRECTED,
            $student,
            'The sampling process was clarified.'
        );
        $correction = $service->updateStatus(
            $correction,
            ThesisCorrection::STATUS_VALIDATED,
            $supervisor,
            'Correction validated.'
        );

        $this->assertSame($version->id, $correction->thesis_version_id);
        $this->assertSame(ThesisCorrection::STATUS_VALIDATED, $correction->status);
        $this->assertSame([
            ThesisCorrection::STATUS_TO_CORRECT,
            ThesisCorrection::STATUS_CORRECTED,
            ThesisCorrection::STATUS_VALIDATED,
        ], $correction->history->pluck('to_status')->all());
        $this->assertSame('The sampling process was clarified.', $correction->history[1]->remarks);
    }

    private function createThesis(): array
    {
        $studentRole = Role::create(['name' => 'Student', 'slug' => 'student']);
        $supervisorRole = Role::create(['name' => 'Supervisor', 'slug' => 'supervisor']);
        $student = User::create([
            'role_id' => $studentRole->id,
            'first_name' => 'Sally',
            'last_name' => 'Student',
            'email' => 'correction-student@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $supervisor = User::create([
            'role_id' => $supervisorRole->id,
            'first_name' => 'Sam',
            'last_name' => 'Supervisor',
            'email' => 'correction-supervisor@example.test',
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
            'student_number' => 'COR-001',
            'enrollment_date' => '2026-09-01',
            'status' => 'active',
        ]);
        $supervisorProfile = Supervisor::create([
            'user_id' => $supervisor->id,
            'department_id' => $department->id,
            'specialization' => 'Research',
            'max_students' => 5,
            'is_active' => true,
        ]);
        $thesis = Thesis::create([
            'student_id' => $studentProfile->id,
            'supervisor_id' => $supervisorProfile->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'academic_year_id' => $academicYear->id,
            'title' => 'Correction workflow',
            'status' => Thesis::STATUS_DRAFT,
        ]);

        return [$student, $supervisor, $thesis];
    }
}
