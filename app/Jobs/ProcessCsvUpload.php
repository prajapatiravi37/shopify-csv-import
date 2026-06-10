<?php

namespace App\Jobs;

use App\Models\ProductImport;
use App\Models\Upload;
use App\Notifications\ImportCompletedNotification;
use App\Services\CsvParserService;
use App\Services\ImportLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ProcessCsvUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Upload $upload) {}

    public function handle(CsvParserService $csvParser, ImportLogService $logger): void
    {
        $upload = $this->upload->fresh();

        $upload->update([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ]);

        $logger->log('upload_started', "Processing upload #{$upload->id}", 'info', $upload);

        try {
            $filePath = storage_path('app/private/'.$upload->stored_path);
            $rows = $csvParser->parse($filePath);

            $upload->update(['total_rows' => count($rows)]);

            foreach ($rows as $row) {
                $productImport = ProductImport::create([
                    'upload_id' => $upload->id,
                    'row_number' => $row['row_number'],
                    'handle' => $row['data']['Handle'] ?? null,
                    'title' => $row['data']['Title'] ?? null,
                    'variant_sku' => $row['data']['Variant SKU'] ?? null,
                    'status' => 'pending',
                    'raw_data' => $row['data'],
                ]);

                ImportProductToShopify::dispatch($productImport);
            }

            $logger->log(
                'upload_queued',
                "Queued {$upload->total_rows} products for Shopify import",
                'info',
                $upload
            );
        } catch (Throwable $exception) {
            $upload->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $logger->log(
                'upload_failed',
                $exception->getMessage(),
                'error',
                $upload,
                context: ['trace' => $exception->getTraceAsString()]
            );

            $this->notifyFailure($upload, $exception->getMessage());

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $upload = $this->upload->fresh();

        if ($upload && $upload->status !== 'failed') {
            $upload->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage() ?? 'Unknown queue failure',
                'completed_at' => now(),
            ]);
        }
    }

    private function notifyFailure(Upload $upload, string $message): void
    {
        $email = config('mail.admin_address');

        if (! $email) {
            return;
        }

        Notification::route('mail', $email)
            ->notify(new ImportCompletedNotification($upload, true, $message));
    }
}
