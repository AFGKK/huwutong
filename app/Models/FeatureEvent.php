<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureEvent extends Model
{
    protected $fillable = [
        'feature_key',
        'feature_name',
        'category',
        'action',
        'user_id',
        'customer_id',
        'session_id',
        'ip_address',
        'user_agent',
        'page_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByFeature($query, string $featureKey)
    {
        return $query->where('feature_key', $featureKey);
    }

    public function scopeByCategory($query, ?string $category)
    {
        if ($category) {
            return $query->where('category', $category);
        }
        return $query;
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
