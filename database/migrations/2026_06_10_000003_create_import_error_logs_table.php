<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_error_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level')->default('error');
            $table->string('event');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['upload_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_error_logs');
    }
};
