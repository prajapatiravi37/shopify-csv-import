<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUploadRequest;
use App\Jobs\ProcessCsvUpload;
use App\Models\Upload;
use App\Services\ImportLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function __construct(private readonly ImportLogService $logger) {}

    public function store(StoreUploadRequest $request): JsonResponse
    {
        $file = $request->file('csv_file');
        $filename = Str::uuid()->toString().'.csv';
        $path = $file->storeAs('uploads', $filename);

        $upload = Upload::create([
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'status' => 'pending',
        ]);

        $this->logger->log(
            'upload_received',
            "CSV upload received: {$upload->original_filename}",
            'info',
            $upload
        );

        ProcessCsvUpload::dispatch($upload);

        return response()->json([
            'message' => 'CSV uploaded successfully. Import has been queued for processing.',
            'upload' => $upload,
        ], 201);
    }
}
