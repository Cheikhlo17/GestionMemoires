<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Program\StoreProgramRequest;
use App\Http\Requests\Program\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Program::with('department');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            });
        }

        $programs = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => ProgramResource::collection($programs->items()),
            'meta' => [
                'current_page' => $programs->currentPage(),
                'last_page' => $programs->lastPage(),
                'per_page' => $programs->perPage(),
                'total' => $programs->total(),
            ],
        ]);
    }

    public function store(StoreProgramRequest $request): JsonResponse
    {
        $program = Program::create($request->validated());

        return response()->json([
            'message' => 'Program created successfully.',
            'data' => new ProgramResource($program->load('department')),
        ], 201);
    }

    public function update(UpdateProgramRequest $request, Program $program): JsonResponse
    {
        $program->update($request->validated());

        return response()->json([
            'message' => 'Program updated successfully.',
            'data' => new ProgramResource($program->fresh('department')),
        ]);
    }

    public function destroy(Program $program): JsonResponse
    {
        if ($this->authRoleIsNotAdmin($request = request())) {
            abort(403);
        }

        if ($program->students()->exists()) {
            return response()->json([
                'message' => 'This program still has enrolled students and cannot be deleted.',
            ], 422);
        }

        $program->delete();

        return response()->json(['message' => 'Program deleted successfully.']);
    }

    private function authRoleIsNotAdmin(Request $request): bool
    {
        return $request->user()->role?->slug !== 'administrator';
    }
}