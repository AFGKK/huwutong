<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * HSM 硬件安全模块密钥模型
 *
 * M3-79: 存储 HSM 密钥元数据（私钥永远不出 HSM）
 *
 * @mixin IdeHelperHsmKey
 */
class HsmKey extends Model
{
    protected $fillable = [
        'key_label',
        'key_handle',
        'public_key',
        'algorithm',
        'provider',
        'is_active',
        'sign_count',
        'rotated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sign_count' => 'integer',
        'rotated_at' => 'datetime',
    ];
}
