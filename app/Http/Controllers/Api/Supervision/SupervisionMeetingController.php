<?php

namespace App\Http\Controllers\Api\Supervision;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supervision\StoreMeetingActionRequest;
use App\Http\Requests\Supervision\StoreMeetingRequest;
use App\Http\Requests\Supervision\UpdateMeetingActionRequest;
use App\Http\Requests\Supervision\UpdateMeetingRequest;
use App\Http\Resources\SupervisionMeetingActionResource;
use App\Http\Resources\SupervisionMeetingResource;
use App\Models\SupervisionMeeting;
use App\Models\SupervisionMeetingAction;
use App\Models\Thesis;
use App\Services\Interfaces\SupervisionMeetingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupervisionMeetingController extends Controller
{
    public function __construct(protected SupervisionMeetingServiceInterface $meetingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'thesis_id' => ['sometimes', 'integer', 'exists:theses,id'],
            'status' => ['sometimes', 'in:scheduled,completed,cancelled'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $meetings = $this->meetingService->list($request->user(), $filters);

        return response()->json([
            'data' => SupervisionMeetingResource::collection($meetings->items()),
            'meta' => [
                'current_page' => $meetings->currentPage(),
                'last_page' => $meetings->lastPage(),
                'per_page' => $meetings->perPage(),
                'total' => $meetings->total(),
            ],
        ]);
    }

    public function store(StoreMeetingRequest $request, Thesis $thesis): JsonResponse
    {
        $meeting = $this->meetingService->create($thesis, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Supervision meeting scheduled successfully.',
            'data' => new SupervisionMeetingResource($meeting),
        ], 201);
    }

    public function show(SupervisionMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        return response()->json([
            'data' => new SupervisionMeetingResource($meeting->load([
                'participants',
                'actions.assignee',
                'history.changedBy',
            ])),
        ]);
    }

    public function update(UpdateMeetingRequest $request, SupervisionMeeting $meeting): JsonResponse
    {
        $meeting = $this->meetingService->update($meeting, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Supervision meeting updated successfully.',
            'data' => new SupervisionMeetingResource($meeting),
        ]);
    }

    public function storeAction(StoreMeetingActionRequest $request, SupervisionMeeting $meeting): JsonResponse
    {
        $action = $this->meetingService->createAction($meeting, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Meeting action created successfully.',
            'data' => new SupervisionMeetingActionResource($action),
        ], 201);
    }

    public function updateAction(
        UpdateMeetingActionRequest $request,
        SupervisionMeeting $meeting,
        SupervisionMeetingAction $action
    ): JsonResponse {
        if ((int) $action->supervision_meeting_id !== (int) $meeting->id) {
            throw new NotFoundHttpException();
        }

        $action = $this->meetingService->updateAction($meeting, $action, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Meeting action updated successfully.',
            'data' => new SupervisionMeetingActionResource($action),
        ]);
    }
}
