<?php

namespace App\Http\Controllers\Api\Thesis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thesis\StoreThesisCorrectionRequest;
use App\Http\Requests\Thesis\UpdateThesisCorrectionStatusRequest;
use App\Http\Resources\ThesisCorrectionResource;
use App\Models\Thesis;
use App\Models\ThesisCorrection;
use App\Services\Interfaces\ThesisCorrectionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ThesisCorrectionController extends Controller
{
    public function __construct(protected ThesisCorrectionServiceInterface $correctionService)
    {
    }

    public function index(Request $request, Thesis $thesis): JsonResponse
    {
        return response()->json([
            'data' => ThesisCorrectionResource::collection(
                $this->correctionService->list($thesis, $request->user())
            ),
        ]);
    }

    public function store(StoreThesisCorrectionRequest $request, Thesis $thesis): JsonResponse
    {
        $correction = $this->correctionService->create(
            $thesis,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Correction recorded successfully.',
            'data' => new ThesisCorrectionResource($correction),
        ], 201);
    }

    public function updateStatus(
        UpdateThesisCorrectionStatusRequest $request,
        Thesis $thesis,
        ThesisCorrection $correction
    ): JsonResponse {
        if ($correction->thesis_id !== $thesis->id) {
            throw new NotFoundHttpException();
        }

        $correction = $this->correctionService->updateStatus(
            $correction,
            $request->validated('status'),
            $request->user(),
            $request->validated('remarks')
        );

        return response()->json([
            'message' => 'Correction status updated successfully.',
            'data' => new ThesisCorrectionResource($correction),
        ]);
    }
}
