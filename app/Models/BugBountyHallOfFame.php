<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BugBountyHallOfFame extends Model
{
    protected $table = 'bug_bounty_hall_of_fame';

    protected $fillable = [
        'hacker_name', 'hacker_handle', 'avatar_url',
        'reports_count', 'total_bounty', 'rank',
        'bio', 'acknowledged_reports', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'reports_count' => 'integer',
            'total_bounty' => 'decimal:2',
            'acknowledged_reports' => 'array',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeRanked($query)
    {
        return $query->orderBy('sort_order');
    }

    public static function rankLabel(string $rank): string
    {
        return match ($rank) {
            'gold' => '金牌',
            'silver' => '银牌',
            'bronze' => '铜牌',
            'honorable' => '荣誉',
            default => $rank,
        };
    }
}
