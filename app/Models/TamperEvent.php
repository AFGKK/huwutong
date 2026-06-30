<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TamperEvent extends Model
{
    protected $table = 'tamper_events';

    protected $fillable = [
        'license_id', 'license_key', 'event_type', 'severity',
        'event_data', 'source_ip', 'source_fingerprint',
        'is_resolved', 'resolved_at', 'resolution', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
