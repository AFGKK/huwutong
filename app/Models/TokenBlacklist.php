<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Token 黑名单
 *
 * 吊销的 token 会记录在此表，供内省中间件快速检查。
 * 配合 Cache 实现 O(1) 实时吊销检查。
 */
class TokenBlacklist extends Model
{
    protected $fillable = [
        'token_id', 'user_id', 'reason', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }
}
