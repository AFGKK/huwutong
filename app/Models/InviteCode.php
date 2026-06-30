<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteCode extends Model
{
    protected $fillable = [
        'channel_id', 'code',
        'created_by_type', 'created_by_id', 'created_by_email',
        'max_uses', 'used_count', 'last_used_at',
        'expires_at', 'status', 'remarks', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('created_by');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(InviteChannel::class);
    }

    /**
     * 邀请码是否可用
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_uses > 0 && $this->used_count >= $this->max_uses) {
            return false;
        }
        return true;
    }

    /**
     * 使用邀请码（递增计数并记录时间）
     */
    public function consume(): bool
    {
        if (! $this->isValid()) {
            return false;
        }
        $this->increment('used_count');
        $this->update(['last_used_at' => now()]);
        return true;
    }

    /**
     * 生成随机邀请码
     */
    public static function generateCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }
}
