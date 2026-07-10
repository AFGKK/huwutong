<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperFeatureDailySummary
 */
class FeatureDailySummary extends Model
{
    protected $fillable = [
        'snapshot_date',
        'feature_key',
        'feature_name',
        'category',
        'pv',
        'uv',
        'user_count',
        'adoption_rate',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
    ];

    public function scopeByCategory($query, ?string $category)
    {
        if ($category) {
            return $query->where('category', $category);
        }
        return $query;
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('snapshot_date', [$start, $end]);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('snapshot_date', '>=', now()->subDays($days));
    }
}
