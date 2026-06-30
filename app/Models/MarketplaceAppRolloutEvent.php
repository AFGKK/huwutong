<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceAppRolloutEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'rollout_id', 'installation_id', 'tenant_id',
        'event_type', 'message', 'details',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function rollout(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAppRollout::class, 'rollout_id');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAppInstallation::class, 'installation_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeByType($q, $type) { return $q->where('event_type', $type); }
    public function scopeErrors($q) { return $q->where('event_type', 'error'); }
}
