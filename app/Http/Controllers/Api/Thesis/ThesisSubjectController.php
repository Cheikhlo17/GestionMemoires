<?php

namespace App\Http\Controllers\Api\Thesis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thesis\PublishThesisSubjectRequest;
use App\Http\Requests\Thesis\ReviewThesisSubjectRequest;
use App\Http\Requests\Thesis\StoreThesisSubjectRequest;
use App\Http\Requests\Thesis\UpdateThesisSubjectRequest;
use App\Http\Resources\ThesisSubjectResource;
use App\Models\ThesisSubject;
use App\Services\Interfaces\ThesisSubjectServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThesisSubjectController extends Controller
{
    public function __construct(protected ThesisSubjectServiceInterface $subjectService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:draft,submitted,under_review,accepted,rejected,published'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $subjects = $this->subjectService->list($request->user(), $filters);

        return response()->json([
            'data' => ThesisSubjectResource::collection($subjects->items()),
            'meta' => [
                'current_page' => $subjects->currentPage(),
                'last_page' => $subjects->lastPage(),
                'per_page' => $subjects->perPage(),
                'total' => $subjects->total(),
            ],
        ]);
    }

    public function show(Request $request, ThesisSubject $thesisSubject): JsonResponse
    {
        $this->authorize('view', $thesisSubject);

        return response()->json([
            'data' => new ThesisSubjectResource($thesisSubject->load([
                'department',
                'student.user',
                'reviewer',
                'history.changedBy',
            ])),
        ]);
    }

    public function storeProposal(StoreThesisSubjectRequest $request): JsonResponse
    {
        $subject = $this->subjectService->createProposal($request->validated(), $request->user());

        return response()->json([
            'message' => 'Subject proposal created successfully.',
            'data' => new ThesisSubjectResource($subject),
        ], 201);
    }

    public function updateProposal(UpdateThesisSubjectRequest $request, ThesisSubject $thesisSubject): JsonResponse
    {
        $subject = $this->subjectService->updateProposal($thesisSubject, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Subject proposal updated successfully.',
            'data' => new ThesisSubjectResource($subject),
        ]);
    }

    public function publish(PublishThesisSubjectRequest $request): JsonResponse
    {
        $subject = $this->subjectService->publish($request->validated(), $request->user());

        return response()->json([
            'message' => 'Department subject published successfully.',
            'data' => new ThesisSubjectResource($subject),
        ], 201);
    }

    public function submit(Request $request, ThesisSubject $thesisSubject): JsonResponse
    {
        $subject = $this->subjectService->submit($thesisSubject, $request->user());

        return response()->json([
            'message' => 'Subject proposal submitted for review.',
            'data' => new ThesisSubjectResource($subject),
        ]);
    }

    public function select(Request $request, ThesisSubject $thesisSubject): JsonResponse
    {
        $subject = $this->subjectService->select($thesisSubject, $request->user());

        return response()->json([
            'message' => 'Published subject selected successfully.',
            'data' => new ThesisSubjectResource($subject),
        ]);
    }

    public function review(ReviewThesisSubjectRequest $request, ThesisSubject $thesisSubject): JsonResponse
    {
        $subject = $this->subjectService->review(
            $thesisSubject,
            $request->validated('status'),
            $request->user(),
            $request->validated('remarks')
        );

        return response()->json([
            'message' => 'Subject proposal reviewed successfully.',
            'data' => new ThesisSubjectResource($subject),
        ]);
    }
}
