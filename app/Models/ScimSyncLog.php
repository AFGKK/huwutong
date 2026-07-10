<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperScimSyncLog
 */
class ScimSyncLog extends Model
{
    protected $fillable = [
        'scim_config_id', 'tenant_id', 'direction', 'status',
        'total_processed', 'created_count', 'updated_count',
        'deactivated_count', 'error_count', 'errors', 'summary',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function scimConfig(): BelongsTo
    {
        return $this->belongsTo(ScimConfig::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
