<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseWatermark extends Model
{
    protected $table = 'license_watermarks';

    protected $fillable = [
        'license_id', 'watermark_key', 'algorithm',
        'watermark_data', 'forensic_data', 'embed_location', 'embed_type',
        'extraction_attempts', 'last_extracted_at', 'extracted_by',
        'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'watermark_data' => 'array',
            'forensic_data' => 'array',
            'expires_at' => 'datetime',
            'last_extracted_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
