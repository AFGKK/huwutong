<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossTenantShare extends Model
{
    protected $table = 'cross_tenant_shares';

    protected $fillable = [
        'source_tenant_id', 'target_tenant_id',
        'resource_type', 'resource_id',
        'permission', 'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    const RESOURCE_TYPES = ['licenses', 'products', 'templates', 'knowledge'];
    const PERMISSIONS = ['read', 'write', 'admin'];
    const STATUSES = ['active', 'pending', 'revoked'];

    public function sourceTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'source_tenant_id');
    }

    public function targetTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }
}
