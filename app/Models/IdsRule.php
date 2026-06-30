<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IdsRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ids_rules';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'detection_type',
        'conditions',
        'actions',
        'threshold_count',
        'threshold_window_minutes',
        'severity',
        'is_active',
        'is_system',
        'priority',
        'hit_count',
        'last_hit_at',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    const DETECTION_TYPES = [
        'brute_force' => '暴力破解',
        'geo_anomaly' => '地理位置异常',
        'rate_burst' => '请求速率暴增',
        'suspicious_pattern' => '可疑访问模式',
        'ip_reputation' => 'IP 信誉检测',
        'credential_stuffing' => '凭证填充攻击',
    ];

    const SEVERITIES = [
        'info' => '信息',
        'warning' => '警告',
        'critical' => '严重',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function alerts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(IdsAlert::class, 'ids_rule_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 获取默认系统规则
     */
    public static function getSystemRules(): array
    {
        return [
            [
                'slug' => 'brute-force-login',
                'name' => '登录暴力破解检测',
                'detection_type' => 'brute_force',
                'description' => '检测短时间内多次登录失败行为',
                'threshold_count' => 5,
                'threshold_window_minutes' => 5,
                'severity' => 'critical',
                'priority' => 10,
                'is_system' => true,
                'conditions' => [
                    'event_type' => 'login_failed',
                    'group_by' => 'ip_address',
                ],
                'actions' => [
                    ['type' => 'block_ip', 'duration_minutes' => 30],
                    ['type' => 'create_security_event', 'severity' => 'critical'],
                    ['type' => 'notify_admin'],
                ],
            ],
            [
                'slug' => 'geo-anomaly-login',
                'name' => '地理位置异常登录检测',
                'detection_type' => 'geo_anomaly',
                'description' => '检测用户从异常地理位置登录',
                'threshold_count' => 1,
                'threshold_window_minutes' => 0,
                'severity' => 'warning',
                'priority' => 20,
                'is_system' => true,
                'conditions' => [
                    'event_type' => 'geo_anomaly',
                ],
                'actions' => [
                    ['type' => 'notify_user', 'message' => '检测到来自新地理位置的登录'],
                    ['type' => 'require_mfa'],
                ],
            ],
            [
                'slug' => 'api-rate-burst',
                'name' => 'API 请求速率暴增检测',
                'detection_type' => 'rate_burst',
                'description' => '检测 API 请求量在短时间内暴增',
                'threshold_count' => 100,
                'threshold_window_minutes' => 1,
                'severity' => 'warning',
                'priority' => 30,
                'is_system' => true,
                'conditions' => [
                    'metric' => 'api_requests',
                    'operator' => '>',
                ],
                'actions' => [
                    ['type' => 'rate_limit', 'limit_per_minute' => 500],
                    ['type' => 'notify_admin'],
                ],
            ],
            [
                'slug' => 'suspicious-path-traversal',
                'name' => '路径遍历攻击检测',
                'detection_type' => 'suspicious_pattern',
                'description' => '检测路径遍历攻击尝试',
                'threshold_count' => 3,
                'threshold_window_minutes' => 10,
                'severity' => 'critical',
                'priority' => 40,
                'is_system' => true,
                'conditions' => [
                    'pattern' => 'path_traversal',
                    'match' => '\.\.\/|\.\.\\\\|%2e%2e',
                ],
                'actions' => [
                    ['type' => 'block_ip', 'duration_minutes' => 60],
                    ['type' => 'create_security_event', 'severity' => 'critical'],
                    ['type' => 'notify_admin'],
                ],
            ],
            [
                'slug' => 'credential-stuffing',
                'name' => '凭证填充攻击检测',
                'detection_type' => 'credential_stuffing',
                'description' => '检测大量不同账号的快速登录尝试',
                'threshold_count' => 10,
                'threshold_window_minutes' => 5,
                'severity' => 'critical',
                'priority' => 15,
                'is_system' => true,
                'conditions' => [
                    'event_type' => 'login_failed',
                    'group_by' => 'ip_address',
                    'distinct_users' => '>=', 5,
                ],
                'actions' => [
                    ['type' => 'block_ip', 'duration_minutes' => 120],
                    ['type' => 'create_security_event', 'severity' => 'critical'],
                    ['type' => 'notify_admin'],
                ],
            ],
        ];
    }
}
