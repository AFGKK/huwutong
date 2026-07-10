<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLlmHealthCheck
 */
class LlmHealthCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'llm_provider_id', 'is_healthy', 'latency_ms',
        'error_message', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_healthy' => 'boolean',
            'latency_ms' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }
}
