<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginPolicy extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'policy_key', 'value_type',
        'value', 'description', 'is_enabled', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    const POLICIES = [
        'max_attempts' => ['name' => '最大登录尝试次数', 'type' => 'integer', 'default' => '5'],
        'lockout_duration' => ['name' => '锁定时间(分钟)', 'type' => 'integer', 'default' => '30'],
        'password_min_length' => ['name' => '密码最小长度', 'type' => 'integer', 'default' => '8'],
        'password_require_uppercase' => ['name' => '密码需大写字母', 'type' => 'boolean', 'default' => 'true'],
        'password_require_numbers' => ['name' => '密码需数字', 'type' => 'boolean', 'default' => 'true'],
        'password_require_symbols' => ['name' => '密码需特殊字符', 'type' => 'boolean', 'default' => 'false'],
        'mfa_required' => ['name' => '强制 MFA', 'type' => 'boolean', 'default' => 'false'],
        'session_timeout_minutes' => ['name' => '会话超时(分钟)', 'type' => 'integer', 'default' => '480'],
        'session_absolute_timeout' => ['name' => '会话绝对超时(小时)', 'type' => 'integer', 'default' => '24'],
        'session_single_device' => ['name' => '单设备登录', 'type' => 'boolean', 'default' => 'false'],
        'ip_whitelist_enforced' => ['name' => '强制 IP 白名单', 'type' => 'boolean', 'default' => 'false'],
        'geo_restriction' => ['name' => '地域限制(JSON)', 'type' => 'json', 'default' => '[]'],
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
