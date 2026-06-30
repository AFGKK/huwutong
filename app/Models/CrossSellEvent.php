<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossSellEvent extends Model
{
    protected $table = 'cross_sell_events';

    protected $fillable = [
        'cross_sell_recommendation_id', 'event_type', 'event_data',
    ];

    protected function casts(): array
    {
        return ['event_data' => 'array'];
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(CrossSellRecommendation::class, 'cross_sell_recommendation_id');
    }
}
