<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperLicenseContract
 */
class LicenseContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'license_contracts';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'contract_type',
        'conditions',
        'actions',
        'evaluation_mode',
        'custom_expression',
        'grant_template',
        'is_active',
        'is_system',
        'version',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'grant_template' => 'array',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    const CONTRACT_TYPES = [
        'license' => 'License 授权',
        'feature' => '功能授权',
        'api' => 'API 访问',
        'device' => '设备限制',
        'time' => '时段限制',
        'geo' => '地域限制',
        'role' => '角色授权',
        'custom' => '自定义',
    ];

    const EVALUATION_MODES = [
        'all' => '全部满足',
        'any' => '任一满足',
        'custom' => '自定义表达式',
    ];

    const CONDITION_TYPES = [
        'time_window' => '时段窗口',
        'ip_range' => 'IP 范围',
        'geo_location' => '地理位置',
        'device_count' => '设备数量',
        'user_role' => '用户角色',
        'license_status' => 'License 状态',
        'license_type' => 'License 类型',
        'feature_enabled' => '功能启用',
        'subscription_plan' => '订阅套餐',
        'custom_field' => '自定义字段',
        'rate_limit' => '速率限制',
        'concurrent_users' => '并发用户数',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LicenseContractAssignment::class, 'contract_id');
    }

    public function evaluationLogs(): HasMany
    {
        return $this->hasMany(LicenseContractEvaluationLog::class, 'contract_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('contract_type', $type);
    }

    /**
     * 获取系统内置合约种子
     */
    public static function getSystemContracts(): array
    {
        return [
            [
                'slug' => 'standard-device-limit',
                'name' => '标准设备限制合约',
                'contract_type' => 'device',
                'description' => '根据License类型限制可使用设备数量',
                'evaluation_mode' => 'all',
                'priority' => 50,
                'is_system' => true,
                'conditions' => [
                    [
                        'type' => 'device_count',
                        'operator' => 'lte',
                        'field' => 'active_devices',
                        'value_source' => 'license.max_devices',
                        'label' => '设备数不超过上限',
                    ],
                ],
                'actions' => [
                    ['on' => 'denied', 'type' => 'block', 'message' => '设备数已超过授权上限'],
                ],
                'grant_template' => [
                    'max_devices' => ['source' => 'license', 'field' => 'max_devices'],
                ],
            ],
            [
                'slug' => 'business-hours-access',
                'name' => '工作时间访问合约',
                'contract_type' => 'time',
                'description' => '限制仅在工作时间（周一至周五 9:00-18:00）可访问',
                'evaluation_mode' => 'all',
                'priority' => 60,
                'is_system' => true,
                'conditions' => [
                    [
                        'type' => 'time_window',
                        'operator' => 'between',
                        'field' => 'current_time',
                        'days' => [1, 2, 3, 4, 5],
                        'start_time' => '09:00',
                        'end_time' => '18:00',
                        'timezone' => 'Asia/Shanghai',
                        'label' => '工作日 9:00-18:00',
                    ],
                ],
                'actions' => [
                    ['on' => 'denied', 'type' => 'restrict', 'message' => '当前不在允许访问时段'],
                ],
            ],
            [
                'slug' => 'cn-mainland-geo',
                'name' => '中国大陆地域限制合约',
                'contract_type' => 'geo',
                'description' => '限制仅允许中国大陆IP访问',
                'evaluation_mode' => 'all',
                'priority' => 70,
                'is_system' => true,
                'conditions' => [
                    [
                        'type' => 'geo_location',
                        'operator' => 'in',
                        'field' => 'ip_country',
                        'value' => ['CN'],
                        'label' => 'IP所在地为中国',
                    ],
                ],
                'actions' => [
                    ['on' => 'denied', 'type' => 'block', 'message' => '仅允许中国大陆地区访问'],
                ],
            ],
            [
                'slug' => 'premium-feature-gate',
                'name' => '高级功能授权合约',
                'contract_type' => 'feature',
                'description' => '控制高级功能仅对特定套餐开放',
                'evaluation_mode' => 'all',
                'priority' => 40,
                'is_system' => true,
                'conditions' => [
                    [
                        'type' => 'subscription_plan',
                        'operator' => 'in',
                        'field' => 'plan_code',
                        'value' => ['premium', 'enterprise', 'ultimate'],
                        'label' => '订阅套餐为高级版或以上',
                    ],
                    [
                        'type' => 'license_status',
                        'operator' => 'eq',
                        'field' => 'status',
                        'value' => 'active',
                        'label' => 'License 状态为活跃',
                    ],
                ],
                'actions' => [
                    ['on' => 'denied', 'type' => 'restrict', 'message' => '当前套餐不支持此功能'],
                ],
                'grant_template' => [
                    'features' => ['ai_analytics', 'advanced_reports', 'api_access'],
                ],
            ],
            [
                'slug' => 'concurrent-user-limit',
                'name' => '并发用户数限制合约',
                'contract_type' => 'concurrent_users',
                'description' => '限制License的并发使用用户数',
                'evaluation_mode' => 'all',
                'priority' => 55,
                'is_system' => true,
                'conditions' => [
                    [
                        'type' => 'concurrent_users',
                        'operator' => 'lte',
                        'field' => 'active_sessions',
                        'value_source' => 'license.max_users',
                        'label' => '当前并发用户数不超过上限',
                    ],
                ],
                'actions' => [
                    ['on' => 'denied', 'type' => 'block', 'message' => '并发用户数已达上限'],
                ],
            ],
        ];
    }
}
