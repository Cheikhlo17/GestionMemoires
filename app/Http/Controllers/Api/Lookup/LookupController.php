<?php

namespace App\Http\Controllers\Api\Lookup;

use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicYearResource;
use App\Http\Resources\DefenseRoomResource;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\JuryMemberResource;
use App\Http\Resources\ProgramResource;
use App\Http\Resources\SupervisorResource;
use App\Models\AcademicYear;
use App\Models\DefenseRoom;
use App\Models\Department;
use App\Models\JuryMember;
use App\Models\Program;
use App\Models\Supervisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function departments(): JsonResponse
    {
        return response()->json([
            'data' => DepartmentResource::collection(
                Department::where('is_active', true)->orderBy('name')->get()
            ),
        ]);
    }

    public function programs(Request $request): JsonResponse
    {
        $query = Program::with('department')->where('is_active', true);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        return response()->json([
            'data' => ProgramResource::collection($query->orderBy('name')->get()),
        ]);
    }

    public function academicYears(): JsonResponse
    {
        return response()->json([
            'data' => AcademicYearResource::collection(
                AcademicYear::orderByDesc('start_date')->get()
            ),
        ]);
    }

    public function defenseRooms(): JsonResponse
    {
        return response()->json([
            'data' => DefenseRoomResource::collection(
                DefenseRoom::where('is_active', true)->orderBy('name')->get()
            ),
        ]);
    }

    public function juryMembers(Request $request): JsonResponse
    {
        $query = JuryMember::with(['user', 'department'])->where('is_active', true);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        return response()->json([
            'data' => JuryMemberResource::collection($query->get()),
        ]);
    }

    public function supervisors(Request $request): JsonResponse
    {
        $query = Supervisor::with(['user', 'department'])->where('is_active', true);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        return response()->json([
            'data' => SupervisorResource::collection($query->get()),
        ]);
    }
}