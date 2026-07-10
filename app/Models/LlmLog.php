<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLlmLog
 */
class LlmLog extends Model
{
    protected $fillable = [
        'user_type', 'user_id', 'tenant_id', 'llm_provider_id',
        'model', 'function', 'prompt', 'response',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'cost_usd', 'duration_ms', 'http_code', 'success', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost_usd' => 'float',
            'duration_ms' => 'integer',
            'success' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }
}
