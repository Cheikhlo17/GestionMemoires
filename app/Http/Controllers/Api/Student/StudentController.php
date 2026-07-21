<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ListStudentsRequest;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\Interfaces\StudentServiceInterface;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    use AuthorizesRequests;
    public function __construct(protected StudentServiceInterface $studentService)
    {
    }

    public function index(ListStudentsRequest $request): JsonResponse
    {
        $students = $this->studentService->list($request->validated());

        return response()->json([
            'data' => StudentResource::collection($students->items()),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = $this->studentService->create($request->validated());

        return response()->json([
            'message' => 'Student created successfully.',
            'data' => new StudentResource($student->load(['user', 'department', 'program', 'academicYear'])),
        ], 201);
    }

    public function show(Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        return response()->json([
            'data' => new StudentResource($student->load(['user', 'department', 'program', 'academicYear'])),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $student = $this->studentService->update($student, $request->validated());

        return response()->json([
            'message' => 'Student updated successfully.',
            'data' => new StudentResource($student),
        ]);
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->authorize('delete', $student);

        $this->studentService->delete($student);

        return response()->json(['message' => 'Student deleted successfully.']);
    }
}