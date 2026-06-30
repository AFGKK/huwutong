<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OaMessage extends Model
{
    protected $fillable = [
        'account_id',
        'user_id',
        'direction',
        'content',
        'content_type',
        'media_url',
        'is_read',
        'reply_to_id',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(OfficialAccount::class, 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }
}
