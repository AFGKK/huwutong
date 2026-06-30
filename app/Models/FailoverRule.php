<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FailoverRule extends Model
{
    use HasFactory;

    protected $table = 'failover_rules';

    protected $fillable = [
        'tenant_id', 'name',
        'primary_dc_id', 'backup_dc_id',
        'trigger_type', 'trigger_threshold_ms', 'failure_count_threshold',
        'auto_failover', 'is_active',
        'last_failover_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'primary_dc_id' => 'integer',
            'backup_dc_id' => 'integer',
            'trigger_threshold_ms' => 'decimal:2',
            'failure_count_threshold' => 'integer',
            'auto_failover' => 'boolean',
            'is_active' => 'boolean',
            'last_failover_at' => 'datetime',
        ];
    }

    const TRIGGER_TYPES = ['latency', 'down', 'manual'];
    const STATUSES = ['active', 'failover', 'restoring', 'inactive'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function primaryDc(): BelongsTo
    {
        return $this->belongsTo(DataCenter::class, 'primary_dc_id');
    }

    public function backupDc(): BelongsTo
    {
        return $this->belongsTo(DataCenter::class, 'backup_dc_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(FailoverLog::class, 'failover_rule_id');
    }
}
