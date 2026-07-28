<?php

namespace App\Http\Controllers\Api\Defense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Defense\AssignJuryRequest;
use App\Http\Requests\Defense\CalendarRequest;
use App\Http\Requests\Defense\CancelDefenseRequest;
use App\Http\Requests\Defense\RecordResultRequest;
use App\Http\Requests\Defense\StoreDefenseScheduleRequest;
use App\Http\Requests\Defense\UpdateDefenseScheduleRequest;
use App\Http\Resources\DefenseResultResource;
use App\Http\Resources\DefenseScheduleResource;
use App\Models\DefenseSchedule;
use App\Services\Interfaces\DefenseScheduleServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DefenseScheduleController extends Controller
{
    public function __construct(protected DefenseScheduleServiceInterface $defenseService)
    {
    }

    public function calendar(CalendarRequest $request): JsonResponse
    {
        $data = $request->validated();

        $schedules = $this->defenseService->calendar(
            \Carbon\Carbon::parse($data['from']),
            \Carbon\Carbon::parse($data['to']),
            $data
        );

        return response()->json([
            'data' => DefenseScheduleResource::collection($schedules),
        ]);
    }

    public function show(DefenseSchedule $defenseSchedule): JsonResponse
    {
        $this->authorize('view', $defenseSchedule);

        $schedule = $this->defenseService->find($defenseSchedule->id);

        return response()->json(['data' => new DefenseScheduleResource($schedule)]);
    }

    public function store(StoreDefenseScheduleRequest $request): JsonResponse
    {
        $schedule = $this->defenseService->schedule($request->validated(), $request->user());

        return response()->json([
            'message' => 'Defense scheduled successfully.',
            'data' => new DefenseScheduleResource($schedule),
        ], 201);
    }

    public function update(UpdateDefenseScheduleRequest $request, DefenseSchedule $defenseSchedule): JsonResponse
    {
        $schedule = $this->defenseService->reschedule($defenseSchedule, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Defense rescheduled successfully.',
            'data' => new DefenseScheduleResource($schedule),
        ]);
    }

    public function assignJury(AssignJuryRequest $request, DefenseSchedule $defenseSchedule): JsonResponse
    {
        $schedule = $this->defenseService->assignJury($defenseSchedule, $request->validated('jury'));

        return response()->json([
            'message' => 'Jury assigned successfully.',
            'data' => new DefenseScheduleResource($schedule),
        ]);
    }

    public function cancel(CancelDefenseRequest $request, DefenseSchedule $defenseSchedule): JsonResponse
    {
        $schedule = $this->defenseService->cancel($defenseSchedule, $request->validated('reason'), $request->user());

        return response()->json([
            'message' => 'Defense cancelled successfully.',
            'data' => new DefenseScheduleResource($schedule),
        ]);
    }

    public function recordResult(RecordResultRequest $request, DefenseSchedule $defenseSchedule): JsonResponse
    {
        $this->authorize('recordResult', $defenseSchedule);

        $result = $this->defenseService->recordResult($defenseSchedule, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Defense result recorded successfully.',
            'data' => new DefenseResultResource($result),
        ]);
    }

    public function generateReport(DefenseSchedule $defenseSchedule): Response
    {
        $this->authorize('generateReport', $defenseSchedule);

        $schedule = $this->defenseService->find($defenseSchedule->id);

        $pdf = Pdf::loadView('reports.defense-report', ['schedule' => $schedule]);

        return $pdf->download("defense-report-{$schedule->id}.pdf");
    }
}