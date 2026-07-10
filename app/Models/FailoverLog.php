<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperFailoverLog
 */
class FailoverLog extends Model
{
    use HasFactory;

    protected $table = 'failover_logs';

    protected $fillable = [
        'failover_rule_id', 'tenant_id',
        'action', 'from_dc', 'to_dc',
        'trigger_reason', 'is_automatic', 'metrics_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'is_automatic' => 'boolean',
            'metrics_snapshot' => 'array',
        ];
    }

    const ACTIONS = ['failover', 'restore', 'manual_failover', 'manual_restore'];

    public function failoverRule(): BelongsTo
    {
        return $this->belongsTo(FailoverRule::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
