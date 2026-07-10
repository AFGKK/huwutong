<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperAccountingIntegration
 */
class AccountingIntegration extends Model
{
    protected $table = 'accounting_integrations';

    protected $fillable = [
        'tenant_id', 'provider', 'name', 'is_active', 'environment',
        'client_id', 'client_secret', 'access_token', 'refresh_token', 'token_expires_at',
        'api_endpoint', 'company_id', 'username', 'password',
        'sync_config', 'sync_interval_minutes',
        'last_sync_at', 'last_success_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'token_expires_at' => 'datetime',
            'sync_config' => 'array',
            'last_sync_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'client_secret', 'access_token', 'refresh_token', 'password',
    ];

    public const PROVIDERS = [
        'quickbooks' => 'QuickBooks Online',
        'xero'       => 'Xero',
        'yonyou'     => '用友',
        'kingdee'    => '金蝶',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function syncMappings(): HasMany
    {
        return $this->hasMany(AccountingSyncMapping::class, 'integration_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(AccountingSyncLog::class, 'integration_id');
    }

    public function getProviderNameAttribute(): string
    {
        return self::PROVIDERS[$this->provider] ?? $this->provider;
    }

    public function isConnected(): bool
    {
        return !empty($this->access_token) || !empty($this->username);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
