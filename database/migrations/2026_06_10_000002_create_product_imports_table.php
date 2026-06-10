<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('handle')->nullable();
            $table->string('title')->nullable();
            $table->string('variant_sku')->nullable();
            $table->enum('status', ['pending', 'processing', 'successful', 'failed'])->default('pending');
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->boolean('was_updated')->default(false);
            $table->text('error_message')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['upload_id', 'status']);
            $table->index('handle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
    }
};
