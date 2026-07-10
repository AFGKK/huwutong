<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCrmSyncLog
 */
class CrmSyncLog extends Model
{
    protected $table = 'crm_sync_logs';

    protected $fillable = [
        'crm_connection_id', 'sync_type', 'entity_type', 'status',
        'total', 'success', 'failed', 'error_message',
        'result', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo { return $this->belongsTo(CrmConnection::class, 'crm_connection_id'); }
}
