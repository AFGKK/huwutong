<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetentionPolicy extends Model
{
    protected $fillable = [
        'data_source',
        'display_name',
        'retention_days',
        'is_active',
        'is_system',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'retention_days' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * 获取有效保留天数
     */
    public static function getEffectiveDays(string $dataSource): int
    {
        $policy = self::where('data_source', $dataSource)->where('is_active', true)->first();
        if ($policy) {
            return $policy->retention_days;
        }

        return config("audit.retention_days.{$dataSource}", config("audit.retention_days.audit", 365));
    }

    /**
     * 获取所有数据源的保留策略
     */
    public static function getAllPolicies(): array
    {
        $defaults = self::defaultSources();
        $policies = self::where('is_active', true)->get()->keyBy('data_source');

        foreach ($defaults as $source => $default) {
            if (isset($policies[$source])) {
                $defaults[$source] = [
                    'id' => $policies[$source]->id,
                    'data_source' => $source,
                    'display_name' => $policies[$source]->display_name,
                    'retention_days' => $policies[$source]->retention_days,
                    'is_active' => $policies[$source]->is_active,
                    'is_system' => $policies[$source]->is_system,
                    'description' => $policies[$source]->description,
                ];
            }
        }

        return array_values($defaults);
    }

    /**
     * 默认数据源配置
     */
    public static function defaultSources(): array
    {
        return [
            'audit_log' => [
                'data_source' => 'audit_log',
                'display_name' => '审计日志',
                'retention_days' => 365,
                'is_active' => true,
                'is_system' => true,
                'description' => '系统审计操作日志，含 License 状态变更、用户操作等',
            ],
            'security_log' => [
                'data_source' => 'security_log',
                'display_name' => '安全日志',
                'retention_days' => 365,
                'is_active' => true,
                'is_system' => true,
                'description' => '安全事件日志，含登录失败、权限越界等',
            ],
            'error_log' => [
                'data_source' => 'error_log',
                'display_name' => '错误日志',
                'retention_days' => 180,
                'is_active' => true,
                'is_system' => true,
                'description' => '系统错误与异常日志',
            ],
            'system_log' => [
                'data_source' => 'system_log',
                'display_name' => '系统日志',
                'retention_days' => 90,
                'is_active' => true,
                'is_system' => true,
                'description' => '系统操作与状态变更日志',
            ],
            'apm_request' => [
                'data_source' => 'apm_request',
                'display_name' => 'APM 请求',
                'retention_days' => 30,
                'is_active' => true,
                'is_system' => true,
                'description' => 'APM 性能监控请求记录',
            ],
            'webhook_event' => [
                'data_source' => 'webhook_event',
                'display_name' => 'Webhook 事件',
                'retention_days' => 90,
                'is_active' => true,
                'is_system' => true,
                'description' => 'Webhook 事件记录',
            ],
            'webhook_delivery' => [
                'data_source' => 'webhook_delivery',
                'display_name' => 'Webhook 投递日志',
                'retention_days' => 60,
                'is_active' => true,
                'is_system' => true,
                'description' => 'Webhook 投递尝试与结果日志',
            ],
            'license' => [
                'data_source' => 'license',
                'display_name' => 'License 记录',
                'retention_days' => 3650,
                'is_active' => true,
                'is_system' => true,
                'description' => 'License 许可记录（含已过期）',
            ],
            'api_endpoint' => [
                'data_source' => 'api_endpoint',
                'display_name' => 'API 端点文档',
                'retention_days' => 730,
                'is_active' => true,
                'is_system' => true,
                'description' => 'API 文档版本与历史端点记录',
            ],
        ];
    }
}
