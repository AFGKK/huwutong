<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WatermarkTraceAudit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'watermark_id', 'license_id', 'trace_type', 'source',
        'leak_url', 'leak_screenshot', 'trace_result',
        'confidence', 'notes', 'operator_id',
    ];

    protected function casts(): array
    {
        return [
            'trace_result' => 'array',
        ];
    }

    public function watermark(): BelongsTo
    {
        return $this->belongsTo(LicenseWatermark::class, 'watermark_id');
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
