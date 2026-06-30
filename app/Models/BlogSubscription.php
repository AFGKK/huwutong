<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Blog/Changelog 邮件订阅模型
 *
 * @m3-57 DevBlog
 */
class BlogSubscription extends Model
{
    protected $fillable = [
        'email',
        'name',
        'subscribed_types',  // ['blog','changelog','release_note']
        'frequency',          // instant, daily, weekly
        'verified_at',
        'unsubscribed_at',
        'token',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_types' => 'array',
            'verified_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * 范围：已验证
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at')->whereNull('unsubscribed_at');
    }

    /**
     * 范围：订阅指定类型
     */
    public function scopeSubscribesTo($query, string $type)
    {
        return $query->whereJsonContains('subscribed_types', $type);
    }

    /**
     * 是否已验证
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null && $this->unsubscribed_at === null;
    }
}
