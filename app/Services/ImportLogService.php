<?php

namespace App\Services;

use App\Models\ImportErrorLog;
use App\Models\ProductImport;
use App\Models\Upload;
use Illuminate\Support\Facades\Log;

class ImportLogService
{
    public function log(
        string $event,
        string $message,
        string $level = 'info',
        ?Upload $upload = null,
        ?ProductImport $productImport = null,
        array $context = []
    ): ImportErrorLog {
        $channel = $level === 'error' ? 'import_errors' : 'import';

        Log::channel($channel)->log($level, $message, array_merge($context, [
            'event' => $event,
            'upload_id' => $upload?->id,
            'product_import_id' => $productImport?->id,
        ]));

        return ImportErrorLog::create([
            'upload_id' => $upload?->id,
            'product_import_id' => $productImport?->id,
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context' => $context ?: null,
        ]);
    }
}
