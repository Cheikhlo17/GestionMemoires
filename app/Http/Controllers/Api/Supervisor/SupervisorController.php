<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supervisor\ListSupervisorsRequest;
use App\Http\Requests\Supervisor\StoreSupervisorRequest;
use App\Http\Requests\Supervisor\UpdateSupervisorRequest;
use App\Http\Resources\SupervisorResource;
use App\Models\Supervisor;
use App\Services\Interfaces\SupervisorServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorController extends Controller
{
    public function __construct(protected SupervisorServiceInterface $supervisorService)
    {
    }

    public function index(ListSupervisorsRequest $request): JsonResponse
    {
        $supervisors = $this->supervisorService->list($request->validated());

        return response()->json([
            'data' => SupervisorResource::collection($supervisors->items()),
            'meta' => [
                'current_page' => $supervisors->currentPage(),
                'last_page' => $supervisors->lastPage(),
                'per_page' => $supervisors->perPage(),
                'total' => $supervisors->total(),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $supervisor = Supervisor::with(['user', 'department'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$supervisor) {
            return response()->json(['message' => 'No supervisor profile found for this account.'], 404);
        }

        return response()->json(['data' => new SupervisorResource($supervisor)]);
    }

    public function store(StoreSupervisorRequest $request): JsonResponse
    {
        $supervisor = $this->supervisorService->create($request->validated());

        return response()->json([
            'message' => 'Supervisor created successfully.',
            'data' => new SupervisorResource($supervisor->load(['user', 'department'])),
        ], 201);
    }

    public function show(Supervisor $supervisor): JsonResponse
    {
        $this->authorize('view', $supervisor);

        return response()->json([
            'data' => new SupervisorResource($supervisor->load(['user', 'department'])),
        ]);
    }

    public function update(UpdateSupervisorRequest $request, Supervisor $supervisor): JsonResponse
    {
        $supervisor = $this->supervisorService->update($supervisor, $request->validated());

        return response()->json([
            'message' => 'Supervisor updated successfully.',
            'data' => new SupervisorResource($supervisor),
        ]);
    }

    public function destroy(Supervisor $supervisor): JsonResponse
    {
        $this->authorize('delete', $supervisor);

        try {
            $this->supervisorService->delete($supervisor);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Supervisor deleted successfully.']);
    }
}