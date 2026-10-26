<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmCsvImportRequest;
use App\Http\Requests\Admin\CsvImportRequest;
use App\Services\CsvImportService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvImportController extends Controller
{
    public function __construct(protected CsvImportService $importService)
    {
    }

    public function template(string $type): StreamedResponse
    {
        $headers = $this->importService->headers($type);

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('The CSV template could not be generated.');
            }

            fputcsv($output, $headers);
            fclose($output);
        }, "{$type}-template.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function preview(CsvImportRequest $request, string $type): JsonResponse
    {
        return response()->json([
            'data' => $this->importService->preview($request->file('file'), $type),
        ]);
    }

    public function confirm(ConfirmCsvImportRequest $request, string $type): JsonResponse
    {
        $result = $this->importService->confirm(
            $request->file('file'),
            $type,
            $request->validated('preview_hash')
        );

        if (!$result['imported']) {
            return response()->json([
                'message' => 'The import was not applied because one or more rows are invalid.',
                'data' => $result['report'],
            ], 422);
        }

        return response()->json([
            'message' => 'CSV import completed. Duplicate rows were skipped.',
            'data' => [
                'created_count' => $result['created_count'],
                'duplicate_count' => $result['duplicate_count'],
                'report' => $result['report'],
            ],
        ]);
    }
}
