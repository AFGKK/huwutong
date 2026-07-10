<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMarketplaceAppRolloutTenant
 */
class MarketplaceAppRolloutTenant extends Model
{
    use HasFactory;

    protected $fillable = ['rollout_id', 'tenant_id', 'included'];

    protected function casts(): array
    {
        return ['included' => 'boolean'];
    }

    public function rollout(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAppRollout::class, 'rollout_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeIncluded($q) { return $q->where('included', true); }
    public function scopeExcluded($q) { return $q->where('included', false); }
}
