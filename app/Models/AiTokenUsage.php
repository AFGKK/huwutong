<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperAiTokenUsage
 */
class AiTokenUsage extends Model
{
    protected $table = 'ai_token_usages';

    protected $fillable = [
        'usage_type', 'usage_id', 'model', 'tokens', 'requests', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function usage(): MorphTo
    {
        return $this->morphTo();
    }
}
