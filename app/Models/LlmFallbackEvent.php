<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLlmFallbackEvent
 */
class LlmFallbackEvent extends Model
{
    protected $fillable = [
        'llm_provider_id', 'event_type',
        'from_provider', 'to_provider',
        'reason', 'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }
}
