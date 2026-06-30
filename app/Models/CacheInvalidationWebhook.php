<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CacheInvalidationWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'url',
        'secret',
        'subscribed_types',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_types' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isSubscribed(string $type): bool
    {
        if (empty($this->subscribed_types)) {
            return true; // 未限制则订阅所有
        }
        return in_array($type, $this->subscribed_types);
    }
}
