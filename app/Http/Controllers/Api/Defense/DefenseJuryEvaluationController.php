<?php

namespace App\Http\Controllers\Api\Defense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Defense\StoreJuryEvaluationRequest;
use App\Http\Resources\DefenseJuryEvaluationResource;
use App\Models\DefenseJuryEvaluation;
use App\Models\DefenseSchedule;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DefenseJuryEvaluationController extends Controller
{
    public function index(DefenseSchedule $defenseSchedule): JsonResponse
    {
        $this->authorize('viewEvaluations', $defenseSchedule);

        $evaluations = $defenseSchedule->juryEvaluations()->with('juryMember.user')->get();

        return response()->json([
            'data' => DefenseJuryEvaluationResource::collection($evaluations),
        ]);
    }

    public function store(StoreJuryEvaluationRequest $request, DefenseSchedule $defenseSchedule): JsonResponse
    {
        $evaluation = DB::transaction(function () use ($request, $defenseSchedule) {
            $schedule = DefenseSchedule::query()->lockForUpdate()->findOrFail($defenseSchedule->id);
            Gate::forUser($request->user())->authorize('submitEvaluation', $schedule);

            if ($schedule->status !== DefenseSchedule::STATUS_SCHEDULED) {
                throw ValidationException::withMessages([
                    'status' => ['Evaluations can only be submitted for a scheduled defense.'],
                ]);
            }

            $juryMember = $schedule->juryMembers()
                ->where('jury_members.user_id', $request->user()->id)
                ->where('jury_members.is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $evaluation = DefenseJuryEvaluation::query()
                ->where('defense_schedule_id', $schedule->id)
                ->where('jury_member_id', $juryMember->id)
                ->first();
            $oldValues = $evaluation?->only(['grade', 'verdict', 'remarks', 'submitted_at']);

            $evaluation = DefenseJuryEvaluation::updateOrCreate(
                [
                    'defense_schedule_id' => $schedule->id,
                    'jury_member_id' => $juryMember->id,
                ],
                [
                    'grade' => $request->validated('grade'),
                    'verdict' => $request->validated('verdict'),
                    'remarks' => $request->validated('remarks'),
                    'submitted_at' => now(),
                ]
            );

            AuditLogger::log(
                action: 'defense_jury_evaluation_submitted',
                modelClass: DefenseJuryEvaluation::class,
                modelId: $evaluation->id,
                oldValues: $oldValues,
                newValues: $evaluation->only(['defense_schedule_id', 'jury_member_id', 'grade', 'verdict', 'remarks']),
                user: $request->user()
            );

            return $evaluation;
        });

        return response()->json([
            'message' => 'Evaluation recorded successfully.',
            'data' => new DefenseJuryEvaluationResource($evaluation->load('juryMember.user')),
        ], 201);
    }
}