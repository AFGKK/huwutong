<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperUserAutoReply
 */
class UserAutoReply extends Model
{
    protected $fillable = [
        'user_id', 'type', 'keyword', 'match_mode', 'reply_content',
        'is_active', 'time_start', 'time_end', 'expires_at', 'reply_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'time_start' => 'datetime',
            'time_end' => 'datetime',
            'expires_at' => 'datetime',
            'reply_count' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeByType($q, $type)
    {
        return $q->where('type', $type);
    }

    /**
     * 检查当前时间是否在规则生效时段内
     */
    public function isInTimeWindow(): bool
    {
        if (!$this->time_start && !$this->time_end) {
            return true;
        }
        $now = now()->format('H:i');
        $start = $this->time_start?->format('H:i') ?? '00:00';
        $end = $this->time_end?->format('H:i') ?? '23:59';
        return $now >= $start && $now <= $end;
    }

    /**
     * 检查是否匹配消息内容
     */
    public function matches(string $content): bool
    {
        if ($this->type !== 'keyword') {
            return true; // away/vacation/busy 类型匹配所有消息
        }
        if (empty($this->keyword)) {
            return true;
        }
        if ($this->match_mode === 'regex') {
            // 非法正则静默降级为 contains 匹配
            set_error_handler(function () {});
            try {
                $result = preg_match('/' . $this->keyword . '/i', $content);
            } finally {
                restore_error_handler();
            }
            return $result === 1;
        }
        return match ($this->match_mode) {
            'exact' => mb_strtolower($content) === mb_strtolower($this->keyword),
            default => mb_stripos($content, $this->keyword) !== false, // contains
        };
    }
}
