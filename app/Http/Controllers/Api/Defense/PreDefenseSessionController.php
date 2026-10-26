<?php

namespace App\Http\Controllers\Api\Defense;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreDefense\StorePreDefensePassageRequest;
use App\Http\Requests\PreDefense\StorePreDefenseSessionRequest;
use App\Http\Requests\PreDefense\UpdatePreDefensePassageRequest;
use App\Http\Requests\PreDefense\UpdatePreDefenseSessionRequest;
use App\Http\Requests\PreDefense\UpdatePreDefenseSessionStatusRequest;
use App\Http\Resources\PreDefensePassageResource;
use App\Http\Resources\PreDefenseSessionResource;
use App\Models\PreDefensePassage;
use App\Models\PreDefenseSession;
use App\Models\Thesis;
use App\Services\Interfaces\PreDefenseServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PreDefenseSessionController extends Controller
{
    public function __construct(protected PreDefenseServiceInterface $preDefenseService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'status' => ['sometimes', 'in:scheduled,completed,cancelled'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $sessions = $this->preDefenseService->list($request->user(), $filters);

        return response()->json([
            'data' => PreDefenseSessionResource::collection($sessions->items()),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    public function store(StorePreDefenseSessionRequest $request): JsonResponse
    {
        $session = $this->preDefenseService->createSession($request->validated(), $request->user());

        return response()->json([
            'message' => 'Pre-defense session created successfully.',
            'data' => new PreDefenseSessionResource($session),
        ], 201);
    }

    public function show(PreDefenseSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        return response()->json([
            'data' => new PreDefenseSessionResource($session->load([
                'department',
                'responsible',
                'passages.student.user',
                'passages.thesis',
                'history.changedBy',
            ])),
        ]);
    }

    public function update(UpdatePreDefenseSessionRequest $request, PreDefenseSession $session): JsonResponse
    {
        $session = $this->preDefenseService->updateSession($session, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Pre-defense session updated successfully.',
            'data' => new PreDefenseSessionResource($session),
        ]);
    }

    public function storePassage(StorePreDefensePassageRequest $request, PreDefenseSession $session): JsonResponse
    {
        $passage = $this->preDefenseService->addPassage($session, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Student passage added successfully.',
            'data' => new PreDefensePassageResource($passage),
        ], 201);
    }

    public function updatePassage(
        UpdatePreDefensePassageRequest $request,
        PreDefenseSession $session,
        PreDefensePassage $passage
    ): JsonResponse {
        if ((int) $passage->pre_defense_session_id !== (int) $session->id) {
            throw new NotFoundHttpException();
        }

        $passage = $this->preDefenseService->updatePassage($session, $passage, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Student passage updated successfully.',
            'data' => new PreDefensePassageResource($passage),
        ]);
    }

    public function updateStatus(
        UpdatePreDefenseSessionStatusRequest $request,
        PreDefenseSession $session
    ): JsonResponse {
        $session = $this->preDefenseService->updateSessionStatus(
            $session,
            $request->validated('status'),
            $request->user()
        );

        return response()->json([
            'message' => 'Pre-defense session status updated successfully.',
            'data' => new PreDefenseSessionResource($session),
        ]);
    }
}
