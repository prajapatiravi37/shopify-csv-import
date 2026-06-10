<?php

namespace Tests\Feature;

use App\Jobs\ProcessCsvUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_upload_is_accepted_and_queued(): void
    {
        Queue::fake();
        Storage::fake('local');

        $csv = UploadedFile::fake()->createWithContent('products.csv', implode("\n", [
            'Handle,Title,Variant SKU,Variant Price',
            'test-handle,Test Product,SKU-001,19.99',
        ]));

        $response = $this->postJson('/api/uploads', [
            'csv_file' => $csv,
        ]);

        $response->assertCreated()
            ->assertJsonPath('upload.original_filename', 'products.csv')
            ->assertJsonPath('upload.status', 'pending');

        Queue::assertPushed(ProcessCsvUpload::class);
    }

    public function test_non_csv_upload_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/uploads', [
            'csv_file' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['csv_file']);
    }
}
