<?php

namespace App\Http\Controllers\Api\Thesis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thesis\AssignSupervisorRequest;
use App\Http\Requests\Thesis\ChangeStatusRequest;
use App\Http\Requests\Thesis\ListThesesRequest;
use App\Http\Requests\Thesis\StoreThesisRequest;
use App\Http\Requests\Thesis\UpdateThesisRequest;
use App\Http\Resources\ThesisResource;
use App\Models\Student;
use App\Models\Thesis;
use App\Services\Interfaces\ThesisServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThesisController extends Controller
{
    public function __construct(protected ThesisServiceInterface $thesisService)
    {
    }

    public function index(ListThesesRequest $request): JsonResponse
    {
        $theses = $this->thesisService->list($request->validated());

        return response()->json([
            'data' => ThesisResource::collection($theses->items()),
            'meta' => [
                'current_page' => $theses->currentPage(),
                'last_page' => $theses->lastPage(),
                'per_page' => $theses->perPage(),
                'total' => $theses->total(),
            ],
        ]);
    }

    public function store(StoreThesisRequest $request): JsonResponse
    {
        $student = Student::where('user_id', $request->user()->id)->firstOrFail();

        $thesis = $this->thesisService->create([
            ...$request->validated(),
            'student_id' => $student->id,
        ]);

        return response()->json([
            'message' => 'Thesis draft created successfully.',
            'data' => new ThesisResource($thesis->load(['student.user', 'department', 'program', 'academicYear'])),
        ], 201);
    }

    public function show(Thesis $thesis): JsonResponse
    {
        $this->authorize('view', $thesis);

        $thesis = $this->thesisService->find($thesis->id);

        return response()->json(['data' => new ThesisResource($thesis)]);
    }

    public function update(UpdateThesisRequest $request, Thesis $thesis): JsonResponse
    {
        $thesis = $this->thesisService->update($thesis, $request->validated());

        return response()->json([
            'message' => 'Thesis updated successfully.',
            'data' => new ThesisResource($thesis),
        ]);
    }

    public function destroy(Thesis $thesis): JsonResponse
    {
        $this->authorize('delete', $thesis);

        $this->thesisService->delete($thesis);

        return response()->json(['message' => 'Thesis deleted successfully.']);
    }

    public function submit(Request $request, Thesis $thesis): JsonResponse
    {
        $this->authorize('update', $thesis);

        $thesis = $this->thesisService->submit($thesis);

        return response()->json([
            'message' => 'Thesis submitted successfully.',
            'data' => new ThesisResource($thesis),
        ]);
    }

    public function assignSupervisor(AssignSupervisorRequest $request, Thesis $thesis): JsonResponse
    {
        $thesis = $this->thesisService->assignSupervisor($thesis, $request->validated('supervisor_id'));

        return response()->json([
            'message' => 'Supervisor assigned successfully.',
            'data' => new ThesisResource($thesis),
        ]);
    }

    public function changeStatus(ChangeStatusRequest $request, Thesis $thesis): JsonResponse
    {
        $thesis = $this->thesisService->changeStatus(
            $thesis,
            $request->validated('status'),
            $request->user(),
            $request->validated('remarks')
        );

        return response()->json([
            'message' => 'Thesis status updated successfully.',
            'data' => new ThesisResource($thesis),
        ]);
    }
}