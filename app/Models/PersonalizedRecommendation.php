<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PersonalizedRecommendation extends Model
{
    use HasFactory;

    protected $table = 'personalized_recommendations';

    protected $fillable = [
        'tenant_id', 'customer_id',
        'recommendation_type', 'recommendable_id', 'recommendable_type',
        'reason', 'score', 'source',
        'is_dismissed', 'dismissed_at', 'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:4',
            'is_dismissed' => 'boolean',
            'dismissed_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    const TYPES = ['license', 'feature', 'addon', 'article', 'product'];
    const SOURCES = ['rule', 'rfm', 'behavior', 'llm'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function recommendable(): MorphTo { return $this->morphTo(); }
}
