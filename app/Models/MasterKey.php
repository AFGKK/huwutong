<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 加密主密钥版本
 *
 * 用于对 TenantSecret 进行 envelope encryption。
 * 每个密钥由 KMS/Vault 加密保管，解密时需通过 KMS 解密。
 *
 * @mixin IdeHelperMasterKey
 */
class MasterKey extends Model
{
    protected $fillable = [
        'key_id', 'label', 'encrypted_key', 'algorithm',
        'status', 'is_current', 'rotated_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'rotated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true)->where('status', 'active');
    }
}
