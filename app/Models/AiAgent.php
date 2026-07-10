<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAiAgent
 */
class AiAgent extends Model
{
    use SoftDeletes;

    protected $table = 'ai_agents';

    protected $fillable = [
        'tenant_id', 'name', 'agent_id', 'framework', 'capabilities',
        'api_key', 'monthly_token_quota', 'tokens_used',
        'status', 'quota_reset_at', 'webhook_config',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'webhook_config' => 'array',
            'quota_reset_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
