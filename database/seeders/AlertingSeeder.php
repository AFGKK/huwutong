<?php

namespace Database\Seeders;

use App\Models\AlertChannel;
use App\Models\AlertEscalation;
use App\Models\AlertRule;
use Illuminate\Database\Seeder;

class AlertingSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 系统预置规则 ───
        if (!AlertRule::where('slug', 'license-expiry-warning')->exists()) {
            AlertRule::create([
                'name' => '许可证到期警告',
                'slug' => 'license-expiry-warning',
                'description' => '当许可证剩余不足 30 天时触发告警',
                'metric_type' => 'license_expiry',
                'condition_operator' => 'gte',
                'threshold' => 30,
                'duration_minutes' => 0,
                'severity' => 'warning',
                'channels' => ['email', 'slack'],
                'cooldown_minutes' => 1440,
                'max_alert_per_day' => 1,
                'is_active' => true,
                'filters' => null,
            ]);
        }

        if (!AlertRule::where('slug', 'failed-payment-alert')->exists()) {
            AlertRule::create([
                'name' => '支付失败告警',
                'slug' => 'failed-payment-alert',
                'description' => '当支付失败时即时触发告警',
                'metric_type' => 'failed_payment',
                'condition_operator' => 'gte',
                'threshold' => 1,
                'duration_minutes' => 0,
                'severity' => 'critical',
                'channels' => ['email', 'slack', 'dingtalk'],
                'cooldown_minutes' => 60,
                'max_alert_per_day' => 20,
                'is_active' => true,
                'filters' => null,
            ]);
        }

        if (!AlertRule::where('slug', 'quota-exceeded')->exists()) {
            AlertRule::create([
                'name' => '配额超限告警',
                'slug' => 'quota-exceeded',
                'description' => '当用量超过配额 90% 时触发告警',
                'metric_type' => 'quota_exceeded',
                'condition_operator' => 'gte',
                'threshold' => 90,
                'duration_minutes' => 10,
                'severity' => 'warning',
                'channels' => ['email'],
                'cooldown_minutes' => 360,
                'max_alert_per_day' => 5,
                'is_active' => true,
                'filters' => null,
            ]);
        }

        if (!AlertRule::where('slug', 'certificate-expiry')->exists()) {
            AlertRule::create([
                'name' => 'SSL 证书到期告警',
                'slug' => 'certificate-expiry',
                'description' => '当 SSL 证书剩余不足 7 天时触发告警',
                'metric_type' => 'certificate_expiry',
                'condition_operator' => 'gte',
                'threshold' => 7,
                'duration_minutes' => 0,
                'severity' => 'critical',
                'channels' => ['email', 'slack'],
                'cooldown_minutes' => 1440,
                'max_alert_per_day' => 1,
                'is_active' => true,
                'filters' => null,
            ]);
        }

        if (!AlertRule::where('slug', 'audit-anomaly')->exists()) {
            AlertRule::create([
                'name' => '审计异常告警',
                'slug' => 'audit-anomaly',
                'description' => '当审计系统检测到异常行为时触发告警',
                'metric_type' => 'audit_anomaly',
                'condition_operator' => 'gte',
                'threshold' => 1,
                'duration_minutes' => 0,
                'severity' => 'warning',
                'channels' => ['slack', 'dingtalk'],
                'cooldown_minutes' => 120,
                'max_alert_per_day' => 10,
                'is_active' => true,
                'filters' => null,
            ]);
        }

        $this->command->info('Seeded 5 system alert rules.');

        // ─── 演示通知渠道 ───
        if (!AlertChannel::where('name', '演示 Slack 通知')->exists()) {
            AlertChannel::create([
                'name' => '演示 Slack 通知',
                'type' => 'slack',
                'config' => ['webhook_url' => 'https://hooks.slack.com/services/demo/webhook'],
                'description' => '演示用 Slack 通知渠道（需替换 Webhook URL）',
                'is_enabled' => true,
                'is_default' => true,
                'sort_order' => 1,
            ]);
        }

        if (!AlertChannel::where('name', '演示钉钉通知')->exists()) {
            AlertChannel::create([
                'name' => '演示钉钉通知',
                'type' => 'dingtalk',
                'config' => ['webhook_url' => 'https://oapi.dingtalk.com/robot/send?access_token=demo'],
                'description' => '演示用钉钉通知渠道（需替换 Token）',
                'is_enabled' => true,
                'is_default' => false,
                'sort_order' => 2,
            ]);
        }

        if (!AlertChannel::where('name', '演示飞书通知')->exists()) {
            AlertChannel::create([
                'name' => '演示飞书通知',
                'type' => 'feishu',
                'config' => ['webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/demo'],
                'description' => '演示用飞书通知渠道（需替换 Webhook）',
                'is_enabled' => true,
                'is_default' => false,
                'sort_order' => 3,
            ]);
        }

        $this->command->info('Seeded 3 demo notification channels.');

        // ─── 演示升级策略 ───
        if (!AlertEscalation::where('name', 'Lv.1 通知管理员')->exists()) {
            AlertEscalation::create([
                'name' => 'Lv.1 通知管理员',
                'escalation_level' => 1,
                'after_minutes' => 30,
                'notify_type' => 'slack',
                'notify_target' => ['webhook_url' => 'https://hooks.slack.com/services/demo/escalation'],
                'message_template' => '[升级 Lv.1] {title}: {message}',
                'escalate_action' => 'notify_admin',
                'is_enabled' => true,
            ]);
        }

        if (!AlertEscalation::where('name', 'Lv.2 创建工单')->exists()) {
            AlertEscalation::create([
                'name' => 'Lv.2 创建工单',
                'escalation_level' => 2,
                'after_minutes' => 120,
                'notify_type' => 'email',
                'notify_target' => ['emails' => ['admin@huwutong.com']],
                'message_template' => '[升级 Lv.2] 告警持续超过 {time}，请立即处理: {title}',
                'escalate_action' => 'create_ticket',
                'is_enabled' => true,
            ]);
        }

        $this->command->info('Seeded 2 escalation policies.');
    }
}
