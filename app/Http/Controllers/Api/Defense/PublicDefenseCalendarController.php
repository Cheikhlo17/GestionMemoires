<?php

namespace App\Http\Controllers\Api\Defense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Defense\PublicDefenseCalendarRequest;
use App\Http\Resources\PublicDefenseScheduleResource;
use App\Models\DefenseSchedule;
use Illuminate\Http\JsonResponse;

class PublicDefenseCalendarController extends Controller
{
    public function index(PublicDefenseCalendarRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $schedules = DefenseSchedule::query()
            ->with(['thesis.department', 'thesis.program', 'room'])
            ->where('status', DefenseSchedule::STATUS_SCHEDULED)
            ->where('is_public', true)
            ->whereBetween('scheduled_at', [$filters['from'], $filters['to']])
            ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->whereHas(
                'thesis',
                fn ($theses) => $theses->where('department_id', $departmentId)
            ))
            ->when($filters['room_id'] ?? null, fn ($query, $roomId) => $query->where('defense_room_id', $roomId))
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'data' => PublicDefenseScheduleResource::collection($schedules),
        ]);
    }
}
