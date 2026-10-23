<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ThesisNotEligibleForArchivingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\ArchiveThesisRequest;
use App\Http\Requests\Archive\ListArchivesRequest;
use App\Http\Requests\Archive\UpdateArchiveRequest;
use App\Http\Resources\ThesisArchiveAdminResource;
use App\Models\Thesis;
use App\Models\ThesisArchive;
use App\Services\Interfaces\ThesisArchiveServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ThesisArchiveController extends Controller
{
    public function __construct(protected ThesisArchiveServiceInterface $archiveService)
    {
    }

    public function index(ListArchivesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ThesisArchive::class);
        $filters = $request->validated();
        $filters['_viewer_role'] = $request->user()->role?->slug;
        $filters['_viewer_id'] = $request->user()->id;

        $archives = $this->archiveService->listAdmin($filters);

        return response()->json([
            'data' => ThesisArchiveAdminResource::collection($archives->items()),
            'meta' => [
                'current_page' => $archives->currentPage(),
                'last_page' => $archives->lastPage(),
                'per_page' => $archives->perPage(),
                'total' => $archives->total(),
            ],
        ]);
    }

    public function eligibility(Thesis $thesis): JsonResponse
    {
        $this->authorize('archive', [ThesisArchive::class, $thesis]);

        $reasons = $this->archiveService->checkEligibility($thesis);

        return response()->json([
            'eligible' => empty($reasons),
            'reasons' => $reasons,
        ]);
    }

    public function store(ArchiveThesisRequest $request, Thesis $thesis): JsonResponse
    {
        try {
            $archive = $this->archiveService->archive($thesis, $request->validated(), $request->user());
        } catch (ThesisNotEligibleForArchivingException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'reasons' => $e->getReasons(),
            ], 422);
        }

        return response()->json([
            'message' => 'Thesis archived successfully.',
            'data' => new ThesisArchiveAdminResource($archive),
        ], 201);
    }

    public function show(ThesisArchive $archive): JsonResponse
    {
        $this->authorize('view', $archive);

        $archive->load(['thesis.student.user', 'thesis.supervisor.user', 'thesis.department', 'thesis.program', 'thesis.academicYear', 'finalVersion', 'archivedBy', 'defenseSchedule.juryMembers.user', 'defenseSchedule.result']);

        return response()->json(['data' => new ThesisArchiveAdminResource($archive)]);
    }

    public function update(UpdateArchiveRequest $request, ThesisArchive $archive): JsonResponse
    {
        $archive = $this->archiveService->update($archive, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Archive updated successfully.',
            'data' => new ThesisArchiveAdminResource($archive),
        ]);
    }

    public function destroy(Request $request, ThesisArchive $archive): JsonResponse
    {
        $this->authorize('delete', $archive);

        $this->archiveService->delete($archive, $request->user());

        return response()->json(['message' => 'Archive deleted successfully.']);
    }

    public function download(ThesisArchive $archive): StreamedResponse
    {
        $this->authorize('view', $archive);

        $version = $archive->finalVersion;

        return Storage::disk('local')->download($version->file_path, $version->file_name);
    }
}