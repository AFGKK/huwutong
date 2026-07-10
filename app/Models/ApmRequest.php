<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperApmRequest
 */
class ApmRequest extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'method',
        'path',
        'route_name',
        'status_code',
        'duration_ms',
        'db_duration_ms',
        'db_queries',
        'cache_duration_ms',
        'cache_hits',
        'external_duration_ms',
        'external_calls',
        'memory_mb',
        'is_slow',
        'slow_reason',
        'ip',
        'user_id',
        'tenant_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_ms' => 'float',
            'db_duration_ms' => 'float',
            'db_queries' => 'integer',
            'cache_duration_ms' => 'float',
            'cache_hits' => 'integer',
            'external_duration_ms' => 'float',
            'external_calls' => 'integer',
            'memory_mb' => 'float',
            'is_slow' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
