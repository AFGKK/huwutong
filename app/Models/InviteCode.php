<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteCode extends Model
{
    protected $fillable = [
        'code', 'created_by_type', 'created_by_id',
        'max_uses', 'used_count', 'expires_at', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->morphTo('created_by');
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
     * 使用邀请码（递增计数）
     */
    public function consume(): bool
    {
        if (! $this->isValid()) {
            return false;
        }
        $this->increment('used_count');
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
