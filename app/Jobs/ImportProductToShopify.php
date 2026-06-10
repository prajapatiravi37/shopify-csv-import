<?php

namespace App\Jobs;

use App\Models\ProductImport;
use App\Models\Upload;
use App\Notifications\ImportCompletedNotification;
use App\Services\ImportLogService;
use App\Services\ShopifyGraphQLService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ImportProductToShopify implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public ProductImport $productImport) {}

    public function handle(ShopifyGraphQLService $shopify, ImportLogService $logger): void
    {
        $productImport = $this->productImport->fresh(['upload']);

        if (! $productImport || ! $productImport->upload) {
            return;
        }

        $productImport->update(['status' => 'processing']);

        try {
            $result = $shopify->importOrUpdate($productImport->raw_data ?? []);
            $product = $result['product'];
            $variant = $product['variants']['nodes'][0]
                ?? $product['variants']['edges'][0]['node']
                ?? null;

            $productImport->update([
                'status' => 'successful',
                'shopify_product_id' => $product['id'] ?? null,
                'shopify_variant_id' => $variant['id'] ?? null,
                'was_updated' => $result['was_updated'],
                'error_message' => null,
                'processed_at' => now(),
            ]);

            $logger->log(
                $result['was_updated'] ? 'product_updated' : 'product_created',
                "Product '{$productImport->title}' imported successfully",
                'info',
                $productImport->upload,
                $productImport,
                ['shopify_product_id' => $product['id'] ?? null]
            );
        } catch (Throwable $exception) {
            $productImport->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            $logger->log(
                'product_import_failed',
                $exception->getMessage(),
                'error',
                $productImport->upload,
                $productImport
            );

            throw $exception;
        } finally {
            $this->refreshUploadCounters($productImport->upload);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $productImport = $this->productImport->fresh(['upload']);

        if (! $productImport) {
            return;
        }

        if ($productImport->status !== 'failed') {
            $productImport->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage() ?? 'Unknown queue failure',
                'processed_at' => now(),
            ]);
        }

        if ($productImport->upload) {
            $this->refreshUploadCounters($productImport->upload);
        }
    }

    private function refreshUploadCounters(Upload $upload): void
    {
        DB::transaction(function () use ($upload) {
            $upload = Upload::query()->lockForUpdate()->find($upload->id);

            if (! $upload) {
                return;
            }

            $counts = ProductImport::query()
                ->where('upload_id', $upload->id)
                ->selectRaw("
                    SUM(CASE WHEN status IN ('successful', 'failed') THEN 1 ELSE 0 END) as processed,
                    SUM(CASE WHEN status = 'successful' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                ")
                ->first();

            $processed = (int) ($counts->processed ?? 0);
            $successful = (int) ($counts->successful ?? 0);
            $failed = (int) ($counts->failed ?? 0);

            $payload = [
                'processed_rows' => $processed,
                'successful_rows' => $successful,
                'failed_rows' => $failed,
            ];

            if ($upload->total_rows > 0 && $processed >= $upload->total_rows) {
                $payload['status'] = $failed > 0 && $successful === 0 ? 'failed' : 'completed';
                $payload['completed_at'] = now();

                if ($failed > 0) {
                    $payload['error_message'] = "{$failed} product(s) failed to import.";
                }

                $upload->update($payload);

                $this->notifyCompletion($upload->fresh());
            } else {
                $upload->update($payload);
            }
        });
    }

    private function notifyCompletion(Upload $upload): void
    {
        $email = config('mail.admin_address');

        if (! $email) {
            return;
        }

        Notification::route('mail', $email)
            ->notify(new ImportCompletedNotification(
                $upload,
                $upload->failed_rows > 0,
                $upload->error_message
            ));
    }
}
