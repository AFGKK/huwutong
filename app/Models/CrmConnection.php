<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmConnection extends Model
{
    protected $table = 'crm_connections';

    protected $fillable = [
        'tenant_id', 'provider', 'is_connected', 'access_token',
        'refresh_token', 'token_expires_at', 'instance_url',
        'portal_id', 'config', 'status', 'last_error',
        'last_sync_at', 'last_success_at',
    ];

    protected function casts(): array
    {
        return [
            'is_connected' => 'boolean',
            'config' => 'array',
            'token_expires_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function syncLogs(): HasMany { return $this->hasMany(CrmSyncLog::class, 'crm_connection_id'); }
    public function entityMappings(): HasMany { return $this->hasMany(CrmEntityMapping::class, 'crm_connection_id'); }
}
