<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductImport extends Model
{
    protected $fillable = [
        'upload_id',
        'row_number',
        'handle',
        'title',
        'variant_sku',
        'status',
        'shopify_product_id',
        'shopify_variant_id',
        'was_updated',
        'error_message',
        'raw_data',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'was_updated' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function errorLogs(): HasMany
    {
        return $this->hasMany(ImportErrorLog::class);
    }
}
