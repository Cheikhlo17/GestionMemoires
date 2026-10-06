<?php

namespace App\Http\Controllers\Api\Archive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\ListArchivesRequest;
use App\Http\Resources\ThesisArchivePublicResource;
use App\Models\ThesisArchive;
use App\Services\Interfaces\ThesisArchiveServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveController extends Controller
{
    public function __construct(protected ThesisArchiveServiceInterface $archiveService)
    {
    }

    public function index(ListArchivesRequest $request): JsonResponse
    {
        $archives = $this->archiveService->listPublic($request->validated());

        return response()->json([
            'data' => ThesisArchivePublicResource::collection($archives->items()),
            'meta' => [
                'current_page' => $archives->currentPage(),
                'last_page' => $archives->lastPage(),
                'per_page' => $archives->perPage(),
                'total' => $archives->total(),
            ],
        ]);
    }

    public function show(ThesisArchive $archive): JsonResponse
    {
        abort_unless($archive->isPublic(), 404);

        $archive->load(['thesis.student.user', 'thesis.supervisor.user', 'thesis.department', 'thesis.program', 'thesis.academicYear', 'defenseSchedule.juryMembers.user', 'defenseSchedule.result']);

        return response()->json(['data' => new ThesisArchivePublicResource($archive)]);
    }

    public function download(ThesisArchive $archive): StreamedResponse
    {
        abort_unless($archive->isPublic(), 404);

        $version = $archive->finalVersion;

        return Storage::disk('local')->download($version->file_path, $version->file_name);
    }
}