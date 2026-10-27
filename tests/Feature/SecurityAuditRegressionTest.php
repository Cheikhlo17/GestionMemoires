<?php

namespace Tests\Feature;

use App\Events\ThesisSubmitted;
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
use App\Models\ThesisArchive;
use App\Models\ThesisVersion;
use App\Models\User;
use App\Notifications\ThesisSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $studentUser;

    private User $supervisorUser;

    private Student $student;

    private Thesis $thesis;

    private AcademicYear $academicYear;

    private Department $department;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'administrator']);
        $studentRole = Role::create(['name' => 'Student', 'slug' => 'student']);
        $supervisorRole = Role::create(['name' => 'Supervisor', 'slug' => 'supervisor']);

        $this->administrator = $this->createUser($adminRole, 'admin');
        $this->studentUser = $this->createUser($studentRole, 'student');
        $this->supervisorUser = $this->createUser($supervisorRole, 'supervisor');

        $this->department = Department::create([
            'name' => 'Science',
            'code' => 'SCI',
            'is_active' => true,
        ]);
        $this->program = Program::create([
            'department_id' => $this->department->id,
            'name' => 'Science Program',
            'code' => 'SCI-P',
            'degree_level' => 'master',
            'is_active' => true,
        ]);
        $this->academicYear = AcademicYear::create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'is_current' => true,
        ]);
        $supervisor = Supervisor::create([
            'user_id' => $this->supervisorUser->id,
            'department_id' => $this->department->id,
            'title' => 'Lecturer',
            'max_students' => 5,
            'is_active' => true,
        ]);
        $this->student = $this->createStudent($this->studentUser, 'STU-001');
        $this->thesis = $this->createThesis($this->student, $supervisor, 'Student thesis');
    }

    public function test_public_registration_cannot_assign_a_role_and_defaults_to_student(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'New',
            'last_name' => 'Account',
            'email' => 'new-account@example.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role_id' => $this->administrator->role_id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'new-account@example.test']);

        $this->postJson('/api/auth/register', [
            'first_name' => 'New',
            'last_name' => 'Student',
            'email' => 'new-student@example.test',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ])->assertCreated();

        $registeredUser = User::where('email', 'new-student@example.test')->firstOrFail();
        $this->assertSame('student', $registeredUser->role->slug);
    }

    public function test_public_archive_search_never_returns_private_archives(): void
    {
        $version = $this->createVersion($this->thesis, 'application/pdf');
        ThesisArchive::create([
            'thesis_id' => $this->thesis->id,
            'thesis_version_id' => $version->id,
            'keywords' => ['private-search-token'],
            'visibility' => ThesisArchive::VISIBILITY_PRIVATE,
            'archived_by' => $this->administrator->id,
            'archived_at' => now(),
        ]);

        $this->getJson('/api/archives?search=private-search-token&visibility=private')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_archive_version_must_be_a_pdf_belonging_to_the_archived_thesis(): void
    {
        $foreignThesis = $this->createThesis(
            $this->createStudent($this->createUser(Role::where('slug', 'student')->firstOrFail(), 'other'), 'STU-002'),
            null,
            'Other thesis'
        );
        $foreignPdf = $this->createVersion($foreignThesis, 'application/pdf');
        $nonPdf = $this->createVersion($this->thesis, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        Sanctum::actingAs($this->administrator);

        foreach ([$foreignPdf, $nonPdf] as $version) {
            $this->postJson("/api/admin/theses/{$this->thesis->id}/archive", [
                'thesis_version_id' => $version->id,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('thesis_version_id');
        }
    }

    public function test_comment_version_must_belong_to_the_thesis_in_the_url(): void
    {
        $otherThesis = $this->createThesis(
            $this->createStudent($this->createUser(Role::where('slug', 'student')->firstOrFail(), 'comment-other'), 'STU-003'),
            null,
            'Another thesis'
        );
        $foreignVersion = $this->createVersion($otherThesis, 'application/pdf');

        Sanctum::actingAs($this->studentUser);
        $this->postJson("/api/theses/{$this->thesis->id}/comments", [
            'content' => 'A comment',
            'thesis_version_id' => $foreignVersion->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('thesis_version_id');
    }

    public function test_non_administrators_cannot_delete_academic_years(): void
    {
        Sanctum::actingAs($this->studentUser);
        $this->deleteJson("/api/admin/academic-years/{$this->academicYear->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('academic_years', ['id' => $this->academicYear->id]);
    }

    public function test_academic_year_cannot_be_deleted_while_soft_deleted_records_reference_it(): void
    {
        $this->student->delete();

        Sanctum::actingAs($this->administrator);
        $this->deleteJson("/api/admin/academic-years/{$this->academicYear->id}")
            ->assertConflict();

        $this->assertDatabaseHas('academic_years', ['id' => $this->academicYear->id]);
    }

    public function test_department_and_program_deletion_checks_include_soft_deleted_references(): void
    {
        $this->student->delete();

        Sanctum::actingAs($this->administrator);
        $this->deleteJson("/api/admin/programs/{$this->program->id}")
            ->assertUnprocessable();
        $this->deleteJson("/api/admin/departments/{$this->department->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('programs', ['id' => $this->program->id]);
        $this->assertDatabaseHas('departments', ['id' => $this->department->id]);
    }

    public function test_queued_submission_notification_persists_and_sends_through_log_mailer(): void
    {
        $logPath = tempnam(sys_get_temp_dir(), 'suma-mail-');
        $this->assertNotFalse($logPath);

        config([
            'queue.default' => 'sync',
            'mail.default' => 'log',
            'mail.mailers.log.channel' => 'notification_delivery_test',
            'logging.channels.notification_delivery_test' => [
                'driver' => 'single',
                'path' => $logPath,
                'level' => 'debug',
            ],
        ]);

        try {
            event(new ThesisSubmitted($this->thesis->load('supervisor.user')));

            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $this->supervisorUser->id,
                'notifiable_type' => User::class,
                'type' => ThesisSubmittedNotification::class,
            ]);
            $this->assertFileExists($logPath);
            $this->assertStringContainsString($this->thesis->title, file_get_contents($logPath));
        } finally {
            if (is_file($logPath)) {
                unlink($logPath);
            }
        }
    }

    public function test_invalid_thesis_status_transitions_return_explicit_422(): void
    {
        $this->thesis->update(['status' => Thesis::STATUS_APPROVED]);

        Sanctum::actingAs($this->supervisorUser);
        $this->postJson("/api/theses/{$this->thesis->id}/status", [
            'status' => Thesis::STATUS_REVISION_REQUIRED,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'message',
                "Cannot transition thesis status from 'approved' to 'revision_required'."
            );
    }

    public function test_thesis_lists_calendars_and_reports_respect_participation(): void
    {
        $otherStudent = $this->createStudent(
            $this->createUser(Role::where('slug', 'student')->firstOrFail(), 'calendar-other'),
            'STU-004'
        );
        $otherThesis = $this->createThesis($otherStudent, null, 'Other student thesis');
        $room = DefenseRoom::create([
            'name' => 'Room A',
            'building' => 'Main building',
            'capacity' => 20,
            'is_active' => true,
        ]);
        $ownSchedule = $this->createSchedule($this->thesis, $room);
        $otherSchedule = $this->createSchedule($otherThesis, $room);

        Sanctum::actingAs($this->studentUser);
        $this->getJson('/api/theses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->thesis->id);

        $this->getJson('/api/defense-schedules/calendar?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownSchedule->id);

        $this->getJson("/api/defense-schedules/{$otherSchedule->id}/report")
            ->assertForbidden();
    }

    public function test_authenticated_calendar_excludes_inactive_jury_assignments(): void
    {
        $juryRole = Role::create(['name' => 'Jury member', 'slug' => 'jury-member']);
        $juryUser = $this->createUser($juryRole, 'inactive-jury');
        $juryMember = JuryMember::create([
            'user_id' => $juryUser->id,
            'department_id' => $this->department->id,
            'is_active' => false,
        ]);
        $room = DefenseRoom::create([
            'name' => 'Room B',
            'building' => 'Main building',
            'capacity' => 20,
            'is_active' => true,
        ]);
        $schedule = $this->createSchedule($this->thesis, $room);
        $schedule->juryMembers()->attach($juryMember->id, ['role' => 'examiner']);

        Sanctum::actingAs($juryUser);
        $this->getJson('/api/defense-schedules/calendar?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertJsonCount(0, 'data');
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

    private function createStudent(User $user, string $studentNumber): Student
    {
        return Student::create([
            'user_id' => $user->id,
            'department_id' => $this->department->id,
            'program_id' => $this->program->id,
            'academic_year_id' => $this->academicYear->id,
            'student_number' => $studentNumber,
            'enrollment_date' => '2026-09-01',
            'status' => 'active',
        ]);
    }

    private function createThesis(Student $student, ?Supervisor $supervisor, string $title): Thesis
    {
        return Thesis::create([
            'student_id' => $student->id,
            'supervisor_id' => $supervisor?->id,
            'department_id' => $this->department->id,
            'program_id' => $this->program->id,
            'academic_year_id' => $this->academicYear->id,
            'title' => $title,
        ]);
    }

    private function createVersion(Thesis $thesis, string $mimeType): ThesisVersion
    {
        return ThesisVersion::create([
            'thesis_id' => $thesis->id,
            'version_number' => 1,
            'file_path' => "theses/{$thesis->id}/version.pdf",
            'file_name' => 'version.pdf',
            'file_size' => 100,
            'mime_type' => $mimeType,
            'uploaded_by' => $this->studentUser->id,
        ]);
    }

    private function createSchedule(Thesis $thesis, DefenseRoom $room): DefenseSchedule
    {
        return DefenseSchedule::create([
            'thesis_id' => $thesis->id,
            'defense_room_id' => $room->id,
            'scheduled_at' => '2026-11-10 10:00:00',
            'duration_minutes' => 60,
            'status' => DefenseSchedule::STATUS_SCHEDULED,
            'created_by' => $this->administrator->id,
        ]);
    }
}
