<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperDemoSession
 */
class DemoSession extends Model
{
    protected $fillable = [
        'token', 'session_id', 'ip_address', 'user_agent',
        'step', 'current_page', 'completed_actions', 'demo_data',
        'started_at', 'expires_at', 'last_activity_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'completed_actions' => 'array',
            'demo_data' => 'array',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'step' => 'integer',
        ];
    }

    /**
     * 创建新演示会话
     */
    public static function createSession(string $sessionId, string $ip = null, string $ua = null): self
    {
        return static::create([
            'token' => Str::random(64),
            'session_id' => $sessionId,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'step' => 0,
            'completed_actions' => [],
            'demo_data' => [],
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'last_activity_at' => now(),
            'status' => 'active',
        ]);
    }

    /**
     * 会话是否有效
     */
    public function isValid(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    /**
     * 会话是否即将过期（剩余5分钟）
     */
    public function isExpiringSoon(): bool
    {
        return $this->isValid() && $this->expires_at->diffInMinutes(now()) <= 5;
    }

    /**
     * 会话剩余秒数
     */
    public function getRemainingSecondsAttribute(): int
    {
        if (!$this->isValid()) return 0;
        return max(0, now()->diffInSeconds($this->expires_at, false));
    }
}
