<?php

namespace Database\Seeders;

use App\Models\AuditActionDict;
use App\Models\AuditAnalysisSummary;
use App\Models\AuditAnomaly;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuditVisualizationSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 审计操作字典 ───
        $actions = [
            // License 操作
            ['action' => 'license.created', 'category' => 'license', 'label' => '创建许可证'],
            ['action' => 'license.status_changed', 'category' => 'license', 'label' => '变更许可证状态'],
            ['action' => 'license.activated', 'category' => 'license', 'label' => '激活许可证'],
            ['action' => 'license.deactivated', 'category' => 'license', 'label' => '停用许可证'],
            ['action' => 'license.deleted', 'category' => 'license', 'label' => '删除许可证'],
            ['action' => 'license.updated', 'category' => 'license', 'label' => '更新许可证'],
            ['action' => 'license.expired', 'category' => 'license', 'label' => '许可证到期'],
            ['action' => 'license.renewed', 'category' => 'license', 'label' => '续期许可证'],
            // 客户操作
            ['action' => 'customer.created', 'category' => 'customer', 'label' => '创建客户'],
            ['action' => 'customer.updated', 'category' => 'customer', 'label' => '更新客户信息'],
            ['action' => 'customer.deleted', 'category' => 'customer', 'label' => '删除客户'],
            // 用户操作
            ['action' => 'user.login', 'category' => 'auth', 'label' => '用户登录'],
            ['action' => 'user.logout', 'category' => 'auth', 'label' => '用户登出'],
            ['action' => 'user.failed_login', 'category' => 'auth', 'label' => '登录失败'],
            ['action' => 'user.created', 'category' => 'user', 'label' => '创建用户'],
            ['action' => 'user.updated', 'category' => 'user', 'label' => '更新用户'],
            ['action' => 'user.deleted', 'category' => 'user', 'label' => '删除用户'],
            ['action' => 'user.password_changed', 'category' => 'auth', 'label' => '修改密码'],
            ['action' => 'user.mfa_enabled', 'category' => 'security', 'label' => '启用MFA'],
            ['action' => 'user.mfa_disabled', 'category' => 'security', 'label' => '禁用MFA'],
            // 设备操作
            ['action' => 'device.activated', 'category' => 'device', 'label' => '激活设备'],
            ['action' => 'device.deactivated', 'category' => 'device', 'label' => '停用设备'],
            ['action' => 'device.deleted', 'category' => 'device', 'label' => '删除设备'],
            // 产品操作
            ['action' => 'product.created', 'category' => 'product', 'label' => '创建产品'],
            ['action' => 'product.updated', 'category' => 'product', 'label' => '更新产品'],
            ['action' => 'product.deleted', 'category' => 'product', 'label' => '删除产品'],
            // 安全事件
            ['action' => 'security.ip_blocked', 'category' => 'security', 'label' => 'IP 被封锁'],
            ['action' => 'security.suspicious_activity', 'category' => 'security', 'label' => '可疑活动检测'],
            ['action' => 'security.session_terminated', 'category' => 'security', 'label' => '会话终止'],
            // 系统操作
            ['action' => 'system.settings_changed', 'category' => 'system', 'label' => '系统设置变更'],
            ['action' => 'system.backup_created', 'category' => 'system', 'label' => '创建备份'],
            ['action' => 'system.config_updated', 'category' => 'system', 'label' => '配置更新'],
            ['action' => 'system.maintenance_mode', 'category' => 'system', 'label' => '维护模式切换'],
            // API 相关
            ['action' => 'api.key_created', 'category' => 'system', 'label' => '创建 API 密钥'],
            ['action' => 'api.key_revoked', 'category' => 'system', 'label' => '撤销 API 密钥'],
        ];

        foreach ($actions as $action) {
            AuditActionDict::updateOrCreate(
                ['action' => $action['action']],
                $action
            );
        }

        $this->command->info('Seeded audit action dictionary: ' . count($actions) . ' entries.');

        // ─── 演示异常检测记录 ───
        $anomalies = [
            [
                'anomaly_type' => 'spike',
                'severity' => 'critical',
                'metric' => '每日审计日志量',
                'baseline_value' => 1280,
                'actual_value' => 6520,
                'deviation' => 409.38,
                'description' => '今日审计日志量 6520，较近7日均值 1280 突增 409.38%',
                'context' => ['possible_cause' => '批量操作或异常访问'],
                'status' => 'open',
                'detected_at' => now()->subHours(3),
            ],
            [
                'anomaly_type' => 'pattern_change',
                'severity' => 'warning',
                'metric' => 'security 类型日志量',
                'baseline_value' => 45,
                'actual_value' => 210,
                'deviation' => 366.67,
                'description' => '本周 security 日志量 210，较上周 45 变化 366.67%',
                'context' => ['possible_cause' => '安全策略触发增加'],
                'status' => 'acknowledged',
                'detected_at' => now()->subDay(),
                'acknowledged_at' => now()->subHours(12),
            ],
            [
                'anomaly_type' => 'drop',
                'severity' => 'info',
                'metric' => '每日审计日志量',
                'baseline_value' => 1500,
                'actual_value' => 320,
                'deviation' => -78.67,
                'description' => '今日审计日志量 320，较近7日均值 1500 突降 78.67%',
                'context' => ['possible_cause' => '非工作日或系统异常'],
                'status' => 'resolved',
                'detected_at' => now()->subWeek(),
                'acknowledged_at' => now()->subWeek()->addHours(2),
            ],
        ];

        foreach ($anomalies as $data) {
            $data['created_at'] = $data['detected_at'];
            $data['updated_at'] = now();
            AuditAnomaly::create($data);
        }

        $this->command->info('Seeded ' . count($anomalies) . ' demo anomaly records.');
    }
}
