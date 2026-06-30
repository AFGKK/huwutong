<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditActionDict extends Model
{
    protected $table = 'audit_action_dict';

    protected $fillable = [
        'action', 'category', 'label', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    const CATEGORIES = [
        'license' => '许可证',
        'customer' => '客户',
        'user' => '用户',
        'system' => '系统',
        'auth' => '认证',
        'device' => '设备',
        'product' => '产品',
        'security' => '安全',
    ];
}
