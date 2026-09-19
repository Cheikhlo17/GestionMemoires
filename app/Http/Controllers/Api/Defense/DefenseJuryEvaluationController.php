<?php

namespace App\Http\Controllers\Api\Defense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Defense\StoreJuryEvaluationRequest;
use App\Http\Resources\DefenseJuryEvaluationResource;
use App\Models\DefenseJuryEvaluation;
use App\Models\DefenseSchedule;
use App\Models\JuryMember;
use Illuminate\Http\JsonResponse;

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
        $juryMember = JuryMember::where('user_id', $request->user()->id)->firstOrFail();

        $evaluation = DefenseJuryEvaluation::updateOrCreate(
            [
                'defense_schedule_id' => $defenseSchedule->id,
                'jury_member_id' => $juryMember->id,
            ],
            [
                'grade' => $request->validated('grade'),
                'verdict' => $request->validated('verdict'),
                'remarks' => $request->validated('remarks'),
                'submitted_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Evaluation recorded successfully.',
            'data' => new DefenseJuryEvaluationResource($evaluation->load('juryMember.user')),
        ], 201);
    }
}