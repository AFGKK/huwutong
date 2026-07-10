<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAccountingSyncLog
 */
class AccountingSyncLog extends Model
{
    protected $table = 'accounting_sync_logs';

    protected $fillable = [
        'tenant_id', 'integration_id', 'sync_type', 'direction',
        'entity_type', 'total_count', 'success_count', 'fail_count',
        'error_message', 'details', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(AccountingIntegration::class, 'integration_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
