<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperDeepResearchTask
 */
class DeepResearchTask extends Model
{
    protected $fillable = [
        'user_id', 'query', 'sub_questions', 'findings', 'report',
        'status', 'source_count', 'total_tokens', 'progress', 'error_message',
    ];

    protected $casts = [
        'sub_questions' => 'array',
        'findings' => 'array',
        'progress' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeByUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }
}
