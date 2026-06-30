<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSmtpConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'provider',
        'name',
        'host',
        'port',
        'encryption',
        'auth',
        'username',
        'password',
        'from_address',
        'from_name',
        'status',
        'is_primary',
        'priority',
        'failure_count',
        'last_tested_at',
        'last_sent_at',
        'last_failure_at',
        'recovered_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'last_tested_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    protected $hidden = ['password'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SmtpDeliveryLog::class, 'smtp_config_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeByTenant($query, ?int $tenantId)
    {
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }
        return $query;
    }
}
