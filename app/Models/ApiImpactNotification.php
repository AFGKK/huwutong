<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiImpactNotification extends Model
{
    protected $fillable = [
        'api_version_id', 'tenant_id', 'channel', 'status',
        'message', 'context', 'sent_at', 'error_message',
    ];

    protected $casts = [
        'context' => 'array',
        'sent_at' => 'datetime',
    ];

    public function apiVersion(): BelongsTo { return $this->belongsTo(ApiVersion::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
