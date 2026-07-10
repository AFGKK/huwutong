<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperIsolationAuditLog
 */
class IsolationAuditLog extends Model
{
    protected $table = 'isolation_audit_logs';

    protected $fillable = [
        'tenant_id', 'event_type', 'severity',
        'resource_type', 'details',
        'is_resolved', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    const EVENT_TYPES = [
        'quota_breach', 'quota_notify', 'data_access',
        'isolation_change', 'config_change',
    ];

    const SEVERITIES = ['info', 'warning', 'critical'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
