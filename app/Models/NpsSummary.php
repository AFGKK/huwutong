<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperNpsSummary
 */
class NpsSummary extends Model
{
    protected $fillable = [
        'snapshot_date',
        'total_responses',
        'promoters',
        'passives',
        'detractors',
        'nps_score',
        'response_rate',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'nps_score' => 'decimal:1',
    ];

    public function scopeRecent($query, int $days = 90)
    {
        return $query->where('snapshot_date', '>=', now()->subDays($days));
    }
}
