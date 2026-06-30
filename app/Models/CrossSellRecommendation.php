<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrossSellRecommendation extends Model
{
    protected $table = 'cross_sell_recommendations';

    protected $fillable = [
        'tenant_id', 'customer_id', 'strategy', 'recommendation_type',
        'recommendable_type', 'recommendable_id', 'score', 'confidence',
        'reason', 'context', 'status',
        'shown_at', 'clicked_at', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'score' => 'decimal:2',
            'confidence' => 'decimal:2',
            'shown_at' => 'datetime',
            'clicked_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function recommendable(): MorphTo { return $this->morphTo(); }
    public function events(): HasMany { return $this->hasMany(CrossSellEvent::class, 'cross_sell_recommendation_id'); }
}
