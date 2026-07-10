<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperPublicKeyVersion
 */
class PublicKeyVersion extends Model
{
    protected $fillable = [
        'key_version', 'algorithm', 'public_key', 'public_key_pem',
        'is_active', 'is_revoked', 'expires_at', 'revoked_at',
        'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'key_version' => 'integer',
            'is_active' => 'boolean',
            'is_revoked' => 'boolean',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * 获取当前活跃的公钥
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)
            ->where('is_revoked', false)
            ->orderBy('key_version', 'desc')
            ->first();
    }

    /**
     * 获取所有有效公钥（含过期兼容窗口内的）
     */
    public static function getValid(): array
    {
        return static::where('is_revoked', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('key_version', 'desc')
            ->get()
            ->all();
    }
}
