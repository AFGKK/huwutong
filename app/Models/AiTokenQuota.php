<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperAiTokenQuota
 */
class AiTokenQuota extends Model
{
    protected $table = 'ai_token_quotas';

    protected $fillable = [
        'tenant_id', 'quotable_type', 'quotable_id', 'model',
        'monthly_token_limit', 'tokens_used', 'overage_action', 'quota_reset_at',
    ];

    protected function casts(): array
    {
        return [
            'quota_reset_at' => 'datetime',
        ];
    }

    public function quotable(): MorphTo
    {
        return $this->morphTo();
    }
}
