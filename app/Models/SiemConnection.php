<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperSiemConnection
 */
class SiemConnection extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'format',
        'endpoint_url',
        'auth_type',
        'auth_credentials',
        'field_mappings',
        'filters',
        'is_active',
        'auto_push',
        'push_frequency',
        'max_batch_size',
        'last_push_at',
        'last_success_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'field_mappings' => 'array',
            'filters' => 'array',
            'is_active' => 'boolean',
            'auto_push' => 'boolean',
            'last_push_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pushLogs(): HasMany
    {
        return $this->hasMany(SiemPushLog::class, 'siem_connection_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByFormat($query, string $format)
    {
        return $query->where('format', $format);
    }
}
