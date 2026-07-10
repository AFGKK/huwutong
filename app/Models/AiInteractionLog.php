<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAiInteractionLog
 */
class AiInteractionLog extends Model
{
    protected $fillable = [
        'session_id', 'source_type', 'source_id', 'user_id',
        'prompt', 'response', 'model', 'provider', 'temperature',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'response_time_ms', 'quality_score', 'was_helpful',
        'had_hallucination', 'status', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'was_helpful' => 'boolean',
        'had_hallucination' => 'boolean',
    ];

    public function scopeSuccessful($q) { return $q->where('status', 'success'); }
    public function scopeBySource($q, string $type) { return $q->where('source_type', $type); }
}
