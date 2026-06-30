<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelfLearningPattern extends Model
{
    protected $fillable = [
        'pattern_type', 'target', 'current_value', 'suggested_value',
        'confidence', 'evidence', 'status', 'applied_by', 'applied_at',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeByType($q, string $type) { return $q->where('pattern_type', $type); }
}
