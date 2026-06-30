<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 推广链接/码
 */
class ReferralLink extends Model
{
    protected $fillable = [
        'agent_id', 'code', 'name', 'target_url',
        'utm_source', 'utm_medium', 'utm_campaign',
        'click_count', 'conversion_count', 'is_active', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'click_count' => 'integer',
            'conversion_count' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
