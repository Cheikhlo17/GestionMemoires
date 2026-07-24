<?php

namespace App\Http\Controllers\Api\Thesis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thesis\UploadVersionRequest;
use App\Http\Resources\ThesisVersionResource;
use App\Models\Thesis;
use App\Models\ThesisVersion;
use App\Services\Interfaces\ThesisServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ThesisVersionController extends Controller
{
    public function __construct(protected ThesisServiceInterface $thesisService)
    {
    }

    public function index(Thesis $thesis): JsonResponse
    {
        $this->authorize('view', $thesis);

        return response()->json([
            'data' => ThesisVersionResource::collection($thesis->versions()->with('uploader')->get()),
        ]);
    }

    public function store(UploadVersionRequest $request, Thesis $thesis): JsonResponse
    {
        $version = $this->thesisService->uploadVersion(
            $thesis,
            $request->file('file'),
            $request->user(),
            $request->validated('notes')
        );

        return response()->json([
            'message' => 'File uploaded successfully.',
            'data' => new ThesisVersionResource($version->load('uploader')),
        ], 201);
    }

    public function download(Thesis $thesis, ThesisVersion $version): StreamedResponse
    {
        $this->authorize('view', $thesis);

        abort_unless($version->thesis_id === $thesis->id, 404);

        return Storage::disk('local')->download($version->file_path, $version->file_name);
    }
}