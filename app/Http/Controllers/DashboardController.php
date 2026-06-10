<?php

namespace App\Http\Controllers;

use App\Models\ImportErrorLog;
use App\Models\ProductImport;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard');
    }

    public function uploads(): JsonResponse
    {
        $uploads = Upload::query()
            ->withCount([
                'productImports as pending_count' => fn ($query) => $query->where('status', 'pending'),
                'productImports as processing_count' => fn ($query) => $query->where('status', 'processing'),
                'productImports as successful_count' => fn ($query) => $query->where('status', 'successful'),
                'productImports as failed_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->latest()
            ->paginate(10);

        return response()->json($uploads);
    }

    public function show(Upload $upload): JsonResponse
    {
        $upload->load(['productImports' => fn ($query) => $query->orderBy('row_number')]);

        return response()->json($upload);
    }

    public function products(Request $request): JsonResponse
    {
        $query = ProductImport::query()->with('upload:id,original_filename');

        if ($request->filled('upload_id')) {
            $query->where('upload_id', $request->integer('upload_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $products = $query->latest()->paginate(20);

        return response()->json($products);
    }

    public function logs(Request $request): JsonResponse
    {
        $query = ImportErrorLog::query()
            ->with(['upload:id,original_filename', 'productImport:id,title,handle']);

        if ($request->filled('upload_id')) {
            $query->where('upload_id', $request->integer('upload_id'));
        }

        if ($request->filled('level')) {
            $query->where('level', $request->string('level'));
        }

        $logs = $query->latest()->paginate(25);

        return response()->json($logs);
    }
}
