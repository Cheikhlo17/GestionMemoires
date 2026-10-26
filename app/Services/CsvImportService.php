<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\JuryMember;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CsvImportService
{
    private const MAX_ROWS = 5000;

    public function headers(string $type): array
    {
        return match ($type) {
            'students' => [
                'first_name',
                'last_name',
                'email',
                'phone',
                'student_number',
                'department_code',
                'program_code',
                'academic_year_label',
                'enrollment_date',
            ],
            'jury-members' => [
                'first_name',
                'last_name',
                'email',
                'phone',
                'department_code',
                'specialization',
            ],
            default => throw ValidationException::withMessages(['type' => ['Unsupported import type.']]),
        };
    }

    public function preview(UploadedFile $file, string $type): array
    {
        $headers = $this->headers($type);
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('The uploaded CSV file could not be located.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The uploaded CSV file could not be read.');
        }

        try {
            $actualHeaders = fgetcsv($handle, null, ',', '"', '');
            if (!is_array($actualHeaders) || $actualHeaders === []) {
                throw ValidationException::withMessages(['file' => ['The CSV file is empty.']]);
            }
            $actualHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $actualHeaders[0]);
            if ($actualHeaders !== $headers) {
                throw ValidationException::withMessages([
                    'file' => ['CSV columns must exactly match the provided template; role or other unrecognized columns are not accepted.'],
                ]);
            }

            $rows = [];
            $line = 1;
            $seenEmails = [];
            $seenStudentNumbers = [];

            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null] || count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages([
                        'file' => ['The CSV file exceeds the maximum of ' . self::MAX_ROWS . ' data rows.'],
                    ]);
                }

                if (count($values) !== count($headers)) {
                    $rows[] = [
                        'line' => $line,
                        'valid' => false,
                        'duplicate' => false,
                        'errors' => ['The row has a different number of columns than the header.'],
                        'data' => [],
                    ];
                    continue;
                }

                $data = array_combine($headers, array_map(fn ($value) => trim((string) $value), $values));
                $row = $this->validateRow($data, $type);
                $email = strtolower($data['email']);
                $studentNumber = strtolower($data['student_number'] ?? '');

                if ($row['valid']) {
                    if (isset($seenEmails[$email]) || $this->emailExists($email)) {
                        $row = $this->duplicateRow($row, 'Email already exists in the database or this CSV.');
                    } elseif ($type === 'students'
                        && (isset($seenStudentNumbers[$studentNumber]) || $this->studentNumberExists($studentNumber))) {
                        $row = $this->duplicateRow($row, 'Student number already exists in the database or this CSV.');
                    } else {
                        $seenEmails[$email] = true;
                        if ($type === 'students') {
                            $seenStudentNumbers[$studentNumber] = true;
                        }
                    }
                }

                $rows[] = [
                    'line' => $line,
                    ...$row,
                    'data' => $data,
                ];
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['file' => ['The CSV file contains no data rows.']]);
            }

            return [
                'type' => $type,
                'preview_hash' => $this->fileHash($file),
                'total_rows' => count($rows),
                'valid_rows' => count(array_filter($rows, fn ($row) => $row['valid'])),
                'duplicate_rows' => count(array_filter($rows, fn ($row) => $row['duplicate'])),
                'invalid_rows' => count(array_filter($rows, fn ($row) => !$row['valid'] && !$row['duplicate'])),
                'rows' => $rows,
            ];
        } finally {
            fclose($handle);
        }
    }

    public function confirm(UploadedFile $file, string $type, string $previewHash): array
    {
        if (!hash_equals($this->fileHash($file), $previewHash)) {
            throw ValidationException::withMessages([
                'preview_hash' => ['The uploaded CSV does not match the file that was previewed.'],
            ]);
        }

        $report = $this->preview($file, $type);
        if ($report['invalid_rows'] > 0) {
            return ['imported' => false, 'report' => $report];
        }

        $roleSlug = $type === 'students' ? 'student' : 'jury-member';
        $role = Role::where('slug', $roleSlug)->first();
        if (!$role) {
            throw new RuntimeException("The required '{$roleSlug}' role is not configured.");
        }
        $createdCount = 0;

        DB::transaction(function () use ($report, $type, $role, &$createdCount) {
            foreach ($report['rows'] as $row) {
                if (!$row['valid']) {
                    continue;
                }

                $data = $row['data'];
                $user = User::create([
                    'role_id' => $role->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => strtolower($data['email']),
                    'phone' => $data['phone'] ?: null,
                    'password' => Str::random(64),
                    'is_active' => true,
                ]);

                if ($type === 'students') {
                    $department = Department::whereRaw('LOWER(code) = ?', [strtolower($data['department_code'])])->firstOrFail();
                    $program = Program::whereRaw('LOWER(code) = ?', [strtolower($data['program_code'])])
                        ->where('department_id', $department->id)
                        ->firstOrFail();
                    $academicYear = AcademicYear::whereRaw('LOWER(label) = ?', [strtolower($data['academic_year_label'])])->firstOrFail();

                    Student::create([
                        'user_id' => $user->id,
                        'department_id' => $department->id,
                        'program_id' => $program->id,
                        'academic_year_id' => $academicYear->id,
                        'student_number' => $data['student_number'],
                        'enrollment_date' => $data['enrollment_date'],
                        'status' => 'active',
                    ]);
                } else {
                    $department = Department::whereRaw('LOWER(code) = ?', [strtolower($data['department_code'])])->firstOrFail();

                    JuryMember::create([
                        'user_id' => $user->id,
                        'department_id' => $department->id,
                        'specialization' => $data['specialization'] ?: null,
                        'is_active' => true,
                    ]);
                }

                $createdCount++;
            }
        });

        return [
            'imported' => true,
            'created_count' => $createdCount,
            'duplicate_count' => $report['duplicate_rows'],
            'report' => $report,
        ];
    }

    private function validateRow(array $data, string $type): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_code' => ['required', 'string', 'max:50'],
        ];

        if ($type === 'students') {
            $rules += [
                'student_number' => ['required', 'string', 'max:50'],
                'program_code' => ['required', 'string', 'max:50'],
                'academic_year_label' => ['required', 'string', 'max:50'],
                'enrollment_date' => ['required', 'date'],
            ];
        } else {
            $rules['specialization'] = ['nullable', 'string', 'max:255'];
        }

        $validator = Validator::make($data, $rules);
        $errors = $validator->errors()->all();
        if ($errors === []) {
            $department = Department::whereRaw('LOWER(code) = ?', [strtolower($data['department_code'])])
                ->where('is_active', true)
                ->first();
            if (!$department) {
                $errors[] = 'Department code does not identify an active department.';
            } elseif ($type === 'students') {
                $program = Program::whereRaw('LOWER(code) = ?', [strtolower($data['program_code'])])
                    ->where('department_id', $department->id)
                    ->where('is_active', true)
                    ->first();
                $academicYear = AcademicYear::whereRaw('LOWER(label) = ?', [strtolower($data['academic_year_label'])])->first();

                if (!$program) {
                    $errors[] = 'Program code does not identify an active program in the selected department.';
                }
                if (!$academicYear) {
                    $errors[] = 'Academic year label does not exist.';
                }
            }
        }

        return [
            'valid' => $errors === [],
            'duplicate' => false,
            'errors' => $errors,
        ];
    }

    private function duplicateRow(array $row, string $message): array
    {
        $row['valid'] = false;
        $row['duplicate'] = true;
        $row['errors'][] = $message;

        return $row;
    }

    private function emailExists(string $email): bool
    {
        return User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    private function studentNumberExists(string $studentNumber): bool
    {
        return Student::withTrashed()->whereRaw('LOWER(student_number) = ?', [$studentNumber])->exists();
    }

    private function fileHash(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('The uploaded CSV file could not be located.');
        }

        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('The uploaded CSV file could not be fingerprinted.');
        }

        return $hash;
    }
}
