<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTokenConsumptionRecord
 */
class TokenConsumptionRecord extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'model', 'provider', 'feature',
        'input_tokens', 'output_tokens', 'total_tokens', 'cost',
        'currency', 'session_id', 'request_id', 'cached',
    ];

    protected $casts = [
        'cached' => 'boolean',
        'cost' => 'float',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByPeriod($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    public function scopeByModel($query, string $model)
    {
        return $query->where('model', $model);
    }

    public function scopeByFeature($query, string $feature)
    {
        return $query->where('feature', $feature);
    }
}
