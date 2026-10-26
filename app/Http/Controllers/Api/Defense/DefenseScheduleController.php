<?php

namespace App\Http\Controllers\Api\Defense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Defense\AssignJuryRequest;
use App\Http\Requests\Defense\CalendarRequest;
use App\Http\Requests\Defense\CancelDefenseRequest;
use App\Http\Requests\Defense\RecordResultRequest;
use App\Http\Requests\Defense\StoreDefenseScheduleRequest;
use App\Http\Requests\Defense\UpdateDefensePublicationRequest;
use App\Http\Requests\Defense\UpdateDefenseScheduleRequest;
use App\Http\Resources\DefenseResultResource;
use App\Http\Resources\DefenseScheduleResource;
use App\Models\DefenseSchedule;
use App\Models\JuryMember;
use App\Services\Interfaces\DefenseScheduleServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class DefenseScheduleController extends Controller
{
    public function __construct(protected DefenseScheduleServiceInterface $defenseService)
    {
    }

    public function calendar(CalendarRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['_viewer_role'] = $request->user()->role?->slug;
        $data['_viewer_id'] = $request->user()->id;

        $schedules = $this->defenseService->calendar(
            \Carbon\Carbon::parse($data['from']),
            \Carbon\Carbon::parse($data['to']),
            $data
        );

        return response()->json([
            'data' => DefenseScheduleResource::collection($schedules),
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = DefenseSchedule::with(['thesis.student.user', 'thesis.supervisor.user', 'room', 'juryMembers.user']);

        if ($user->role?->slug === 'jury-member') {
            $juryMember = JuryMember::where('user_id', $user->id)->where('is_active', true)->first();
            $query->whereHas('juryMembers', function ($q) use ($juryMember) {
                $q->where('jury_members.id', $juryMember?->id ?? 0);
            });
        } elseif ($user->role?->slug === 'supervisor') {
            $query->whereHas('thesis.supervisor', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($user->role?->slug === 'student') {
            $query->whereHas('thesis.student', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($user->role?->slug === 'head-of-department') {
            $query->whereHas('thesis.department', function ($q) use ($user) {
                $q->where('head_of_department_id', $user->id);
            });
        } elseif ($user->role?->slug !== 'administrator') {
            $query->whereKey(0);
        }

        $schedules = $query->orderBy('scheduled_at')->get();

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
        $schedule = $this->defenseService->assignJury(
            $defenseSchedule,
            $request->validated('jury'),
            $request->user()
        );

        return response()->json([
            'message' => 'Jury assigned successfully.',
            'data' => new DefenseScheduleResource($schedule),
        ]);
    }

    public function updatePublication(
        UpdateDefensePublicationRequest $request,
        DefenseSchedule $defenseSchedule
    ): JsonResponse {
        $schedule = $this->defenseService->setPublic(
            $defenseSchedule,
            $request->boolean('is_public'),
            $request->user()
        );

        return response()->json([
            'message' => 'Defense publication updated successfully.',
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
        if ($schedule->status !== DefenseSchedule::STATUS_COMPLETED
            || !$schedule->result?->verdict
            || !$schedule->result?->recorded_at
            || !$schedule->result?->recorded_by) {
            throw ValidationException::withMessages([
                'result' => ['An officially recorded final result is required before generating the defense report.'],
            ]);
        }

        $pdf = Pdf::loadView('reports.defense-report', ['schedule' => $schedule]);

        return $pdf->download("defense-report-{$schedule->id}.pdf");
    }
}