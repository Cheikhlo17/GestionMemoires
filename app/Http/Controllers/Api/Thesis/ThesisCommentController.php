<?php

namespace App\Http\Controllers\Api\Thesis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thesis\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Thesis;
use App\Services\Interfaces\ThesisServiceInterface;
use Illuminate\Http\JsonResponse;

class ThesisCommentController extends Controller
{
    public function __construct(protected ThesisServiceInterface $thesisService)
    {
    }

    public function index(Thesis $thesis): JsonResponse
    {
        $this->authorize('view', $thesis);

        return response()->json([
            'data' => CommentResource::collection($thesis->comments()->with('author')->get()),
        ]);
    }

    public function store(StoreCommentRequest $request, Thesis $thesis): JsonResponse
    {
        $comment = $this->thesisService->addComment(
            $thesis,
            $request->user(),
            $request->validated('content'),
            $request->validated('thesis_version_id')
        );

        return response()->json([
            'message' => 'Comment added successfully.',
            'data' => new CommentResource($comment),
        ], 201);
    }
}