<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicYear\StoreAcademicYearRequest;
use App\Http\Requests\AcademicYear\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => AcademicYearResource::collection(AcademicYear::orderByDesc('start_date')->get()),
        ]);
    }

    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        $year = DB::transaction(function () use ($request) {
            $data = $request->validated();

            if ($data['is_current'] ?? false) {
                AcademicYear::query()->update(['is_current' => false]);
            }

            return AcademicYear::create($data);
        });

        return response()->json([
            'message' => 'Academic year created successfully.',
            'data' => new AcademicYearResource($year),
        ], 201);
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        DB::transaction(function () use ($request, $academicYear) {
            $data = $request->validated();

            if ($data['is_current'] ?? false) {
                AcademicYear::where('id', '!=', $academicYear->id)->update(['is_current' => false]);
            }

            $academicYear->update($data);
        });

        return response()->json([
            'message' => 'Academic year updated successfully.',
            'data' => new AcademicYearResource($academicYear->fresh()),
        ]);
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        if ($academicYear->students()->exists()) {
            return response()->json([
                'message' => 'This academic year still has enrolled students and cannot be deleted.',
            ], 422);
        }

        $academicYear->delete();

        return response()->json(['message' => 'Academic year deleted successfully.']);
    }
}