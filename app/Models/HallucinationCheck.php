<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HallucinationCheck extends Model
{
    protected $fillable = [
        'source_type', 'source_id', 'original_text',
        'claims', 'results', 'overall_score', 'verdict',
        'total_claims', 'verified_claims', 'unverifiable_claims', 'contradicted_claims',
    ];

    protected $casts = [
        'claims' => 'array',
        'results' => 'array',
        'overall_score' => 'decimal:2',
    ];

    public function scopeBySource($q, string $type, int $id)
    {
        return $q->where('source_type', $type)->where('source_id', $id);
    }
}
