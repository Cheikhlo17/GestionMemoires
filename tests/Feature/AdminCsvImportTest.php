<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Department $department;

    private Program $program;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();

        $administratorRole = Role::create(['name' => 'Administrator', 'slug' => 'administrator']);
        Role::create(['name' => 'Student', 'slug' => 'student']);
        Role::create(['name' => 'Jury Member', 'slug' => 'jury-member']);
        $this->administrator = User::create([
            'role_id' => $administratorRole->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->department = Department::create([
            'name' => 'Science',
            'code' => 'SCI',
            'is_active' => true,
        ]);
        $this->program = Program::create([
            'department_id' => $this->department->id,
            'name' => 'Science Program',
            'code' => 'SCI-1',
            'degree_level' => 'master',
            'is_active' => true,
        ]);
        $this->academicYear = AcademicYear::create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'is_current' => true,
        ]);

        Sanctum::actingAs($this->administrator);
    }

    public function test_administrator_can_preview_and_confirm_student_import(): void
    {
        $csv = $this->studentCsv([
            ['Ada', 'Lovelace', 'ada@example.test', '5550100', 'STU-001', 'SCI', 'SCI-1', '2026-2027', '2026-09-01'],
        ]);
        $preview = $this->postJson('/api/admin/imports/students/preview', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.duplicate_rows', 0)
            ->assertJsonPath('data.invalid_rows', 0);

        $this->postJson('/api/admin/imports/students/confirm', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            'preview_hash' => $preview->json('data.preview_hash'),
        ])->assertOk()
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.duplicate_count', 0);

        $user = User::where('email', 'ada@example.test')->firstOrFail();
        $this->assertSame('student', $user->role->slug);
        $this->assertDatabaseHas('students', [
            'user_id' => $user->id,
            'student_number' => 'STU-001',
            'department_id' => $this->department->id,
            'program_id' => $this->program->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
    }

    public function test_duplicate_rows_are_reported_and_skipped_while_new_rows_import(): void
    {
        $studentRole = Role::where('slug', 'student')->firstOrFail();
        $existingUser = User::create([
            'role_id' => $studentRole->id,
            'first_name' => 'Existing',
            'last_name' => 'Student',
            'email' => 'existing@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        Student::create([
            'user_id' => $existingUser->id,
            'department_id' => $this->department->id,
            'program_id' => $this->program->id,
            'academic_year_id' => $this->academicYear->id,
            'student_number' => 'STU-001',
            'enrollment_date' => '2026-09-01',
            'status' => 'active',
        ]);

        $csv = $this->studentCsv([
            ['Duplicate', 'Number', 'duplicate@example.test', '', 'STU-001', 'SCI', 'SCI-1', '2026-2027', '2026-09-01'],
            ['New', 'Student', 'new@example.test', '', 'STU-002', 'SCI', 'SCI-1', '2026-2027', '2026-09-01'],
        ]);
        $preview = $this->postJson('/api/admin/imports/students/preview', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.duplicate_rows', 1)
            ->assertJsonPath('data.invalid_rows', 0);

        $this->postJson('/api/admin/imports/students/confirm', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            'preview_hash' => $preview->json('data.preview_hash'),
        ])->assertOk()
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.duplicate_count', 1);

        $this->assertDatabaseMissing('users', ['email' => 'duplicate@example.test']);
        $this->assertDatabaseHas('users', ['email' => 'new@example.test']);
    }

    public function test_invalid_rows_prevent_the_entire_import(): void
    {
        $csv = $this->studentCsv([
            ['Valid', 'Student', 'valid@example.test', '', 'STU-001', 'SCI', 'SCI-1', '2026-2027', '2026-09-01'],
            ['Invalid', 'Student', 'invalid@example.test', '', 'STU-002', 'UNKNOWN', 'SCI-1', '2026-2027', '2026-09-01'],
        ]);
        $preview = $this->postJson('/api/admin/imports/students/preview', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('data.valid_rows', 1)
            ->assertJsonPath('data.invalid_rows', 1);

        $this->postJson('/api/admin/imports/students/confirm', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            'preview_hash' => $preview->json('data.preview_hash'),
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['email' => 'valid@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'invalid@example.test']);
    }

    public function test_confirmation_rejects_a_file_that_differs_from_the_preview(): void
    {
        $previewCsv = $this->studentCsv([
            ['First', 'Student', 'first@example.test', '', 'STU-001', 'SCI', 'SCI-1', '2026-2027', '2026-09-01'],
        ]);
        $differentCsv = $this->studentCsv([
            ['Second', 'Student', 'second@example.test', '', 'STU-002', 'SCI', 'SCI-1', '2026-2027', '2026-09-01'],
        ]);
        $preview = $this->postJson('/api/admin/imports/students/preview', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $previewCsv),
        ])->assertOk();

        $this->postJson('/api/admin/imports/students/confirm', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $differentCsv),
            'preview_hash' => $preview->json('data.preview_hash'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('preview_hash');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_import_endpoints_are_administrator_only_and_role_columns_are_rejected(): void
    {
        $studentRole = Role::where('slug', 'student')->firstOrFail();
        $student = User::create([
            'role_id' => $studentRole->id,
            'first_name' => 'Student',
            'last_name' => 'User',
            'email' => 'student@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($student);
        $this->postJson('/api/admin/imports/students/preview', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $this->studentCsv([])),
        ])->assertForbidden();

        Sanctum::actingAs($this->administrator);
        $csv = "first_name,last_name,email,phone,student_number,department_code,program_code,academic_year_label,enrollment_date,role\n";
        $this->postJson('/api/admin/imports/students/preview', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_administrator_can_import_jury_members_and_download_a_template(): void
    {
        $this->assertSame([
            'first_name',
            'last_name',
            'email',
            'phone',
            'department_code',
            'specialization',
        ], app(CsvImportService::class)->headers('jury-members'));
        $this->get('/api/admin/imports/jury-members/template')->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = "first_name,last_name,email,phone,department_code,specialization\n"
            . "Jury,Member,jury@example.test,5550101,SCI,Water quality\n";
        $preview = $this->postJson('/api/admin/imports/jury-members/preview', [
            'file' => UploadedFile::fake()->createWithContent('jury-members.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('data.valid_rows', 1);

        $this->postJson('/api/admin/imports/jury-members/confirm', [
            'file' => UploadedFile::fake()->createWithContent('jury-members.csv', $csv),
            'preview_hash' => $preview->json('data.preview_hash'),
        ])->assertOk()
            ->assertJsonPath('data.created_count', 1);

        $this->assertDatabaseHas('jury_members', [
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
    }

    private function studentCsv(array $rows): string
    {
        $content = "first_name,last_name,email,phone,student_number,department_code,program_code,academic_year_label,enrollment_date\n";

        foreach ($rows as $row) {
            $content .= implode(',', array_map(
                fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"',
                $row
            )) . "\n";
        }

        return $content;
    }
}
