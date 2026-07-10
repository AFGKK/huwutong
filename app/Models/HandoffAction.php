<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 客服操作日志
 *
 * @mixin IdeHelperHandoffAction
 */
class HandoffAction extends Model
{
    protected $fillable = [
        'handoff_request_id', 'user_id',
        'action', 'note', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function handoffRequest(): BelongsTo
    {
        return $this->belongsTo(HandoffRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
