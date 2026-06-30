<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScimConfig extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'provider', 'enabled',
        'base_url', 'api_token', 'token_type',
        'attribute_mapping', 'options',
        'sync_frequency', 'last_sync_at',
        'last_sync_status', 'last_sync_error',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'attribute_mapping' => 'array',
        'options' => 'array',
        'last_sync_at' => 'datetime',
    ];

    const PROVIDERS = ['generic', 'okta', 'azure', 'onelogin'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(ScimSyncLog::class, 'scim_config_id');
    }

    public function resourceMappings(): HasMany
    {
        return $this->hasMany(ScimResourceMapping::class, 'scim_config_id');
    }
}
