<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpsResponse extends Model
{
    protected $fillable = [
        'survey_id',
        'user_id',
        'score',
        'feedback',
        'best_feature',
        'improvement',
        'category',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(NpsSurvey::class, 'survey_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePromoters($query)
    {
        return $query->whereBetween('score', [9, 10]);
    }

    public function scopePassives($query)
    {
        return $query->whereBetween('score', [7, 8]);
    }

    public function scopeDetractors($query)
    {
        return $query->whereBetween('score', [0, 6]);
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
