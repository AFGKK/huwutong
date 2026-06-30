<?php

namespace Database\Seeders;

use App\Models\AutomationRule;
use App\Models\AutomationWebhook;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AutomationRuleSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 系统规则模板 ───
        $templates = [
            [
                'name' => 'License 过期自动暂停',
                'slug' => 'sys-license-expire-suspend',
                'description' => '当 License 过期时自动暂停并通知管理员',
                'category' => 'license',
                'trigger_type' => 'event',
                'trigger_config' => ['event_type' => 'license.expired'],
                'conditions' => [
                    ['field' => 'license.status', 'operator' => 'eq', 'value' => 'active'],
                ],
                'condition_logic' => 'all',
                'actions' => [
                    ['type' => 'update_license', 'config' => ['action' => 'suspend']],
                    ['type' => 'create_log', 'config' => ['type' => 'license', 'action' => 'auto.suspend', 'description' => 'Rule 自动暂停过期 License: {license_id}']],
                    ['type' => 'notify_admin', 'config' => ['message' => 'License {license_id} 已过期并自动暂停']],
                ],
                'action_execution' => 'sequential',
                'status' => 'active',
                'priority' => 100,
                'is_system' => true,
                'is_template' => false,
            ],
            [
                'name' => '发票逾期催缴通知',
                'slug' => 'sys-invoice-overdue-remind',
                'description' => '发票逾期时发送催缴通知并创建审计日志',
                'category' => 'billing',
                'trigger_type' => 'event',
                'trigger_config' => ['event_type' => 'invoice.overdue'],
                'conditions' => [
                    ['field' => 'invoice.amount_due', 'operator' => 'gt', 'value' => 0],
                ],
                'condition_logic' => 'all',
                'actions' => [
                    ['type' => 'send_email', 'config' => ['template' => 'invoice_overdue', 'subject' => '发票逾期提醒 - {invoice_id}', 'to' => 'customer']],
                    ['type' => 'create_log', 'config' => ['type' => 'billing', 'action' => 'invoice.overdue.reminded', 'description' => '逾期发票催缴通知已发送']],
                ],
                'action_execution' => 'sequential',
                'status' => 'active',
                'priority' => 90,
                'is_system' => true,
                'is_template' => false,
            ],
            [
                'name' => '订阅到期前通知',
                'slug' => 'sys-subscription-expiry-warn',
                'description' => '订阅到期前7天发送提醒邮件',
                'category' => 'billing',
                'trigger_type' => 'event',
                'trigger_config' => ['event_type' => 'subscription.expiring'],
                'conditions' => [],
                'condition_logic' => 'all',
                'actions' => [
                    ['type' => 'send_email', 'config' => ['template' => 'subscription_expiring', 'subject' => '您的订阅即将到期', 'to' => 'customer']],
                ],
                'action_execution' => 'sequential',
                'status' => 'active',
                'priority' => 80,
                'is_system' => true,
                'is_template' => false,
            ],
            [
                'name' => '安全违规自动响应',
                'slug' => 'sys-security-breach-respond',
                'description' => '检测到安全违规时暂停租户并通知管理员',
                'category' => 'security',
                'trigger_type' => 'event',
                'trigger_config' => ['event_type' => 'security.breach'],
                'conditions' => [
                    ['field' => 'severity', 'operator' => 'gte', 'value' => 'high'],
                ],
                'condition_logic' => 'all',
                'actions' => [
                    ['type' => 'suspend_tenant', 'config' => ['reason' => '安全违规自动暂停']],
                    ['type' => 'notify_admin', 'config' => ['message' => '安全违规告警：租户 {tenant_id} 已被暂停']],
                    ['type' => 'create_log', 'config' => ['type' => 'security', 'action' => 'security.auto.suspend', 'description' => '安全违规自动暂停租户']],
                ],
                'action_execution' => 'sequential',
                'status' => 'draft',
                'priority' => 100,
                'is_system' => true,
                'is_template' => false,
            ],
            [
                'name' => '新客户注册欢迎流程',
                'slug' => 'sys-new-customer-welcome',
                'description' => '新客户注册时发送欢迎邮件并通知销售团队',
                'category' => 'customer',
                'trigger_type' => 'event',
                'trigger_config' => ['event_type' => 'customer.created'],
                'conditions' => [],
                'condition_logic' => 'all',
                'actions' => [
                    ['type' => 'send_email', 'config' => ['template' => 'welcome', 'subject' => '欢迎加入！', 'to' => 'customer']],
                    ['type' => 'create_log', 'config' => ['type' => 'customer', 'action' => 'customer.welcome', 'description' => '新客户注册欢迎流程已触发']],
                ],
                'action_execution' => 'sequential',
                'status' => 'active',
                'priority' => 70,
                'is_system' => true,
                'is_template' => false,
            ],
            [
                'name' => '设备激活安全通知',
                'slug' => 'sys-device-activation-alert',
                'description' => '新设备激活时通知管理员',
                'category' => 'security',
                'trigger_type' => 'event',
                'trigger_config' => ['event_type' => 'device.activated'],
                'conditions' => [],
                'condition_logic' => 'all',
                'actions' => [
                    ['type' => 'notify_admin', 'config' => ['message' => '新设备已激活: {device_id}']],
                    ['type' => 'create_log', 'config' => ['type' => 'security', 'action' => 'device.activated', 'description' => '新设备激活通知']],
                ],
                'action_execution' => 'sequential',
                'status' => 'active',
                'priority' => 60,
                'is_system' => true,
                'is_template' => false,
            ],
            [
                'name' => '自定义: 失败重试通知',
                'slug' => 'tpl-failure-retry-notify',
                'description' => '当某个操作失败时可配置重试并通知（模板）',
                'category' => 'system',
                'trigger_type' => 'event',
                'trigger_config' => ['event_type' => 'operation.failed'],
                'conditions' => [
                    ['field' => 'attempts', 'operator' => 'gte', 'value' => 3],
                ],
                'condition_logic' => 'all',
                'actions' => [
                    ['type' => 'notify_admin', 'config' => ['message' => '操作失败已达 {attempts} 次: {operation}']],
                ],
                'action_execution' => 'sequential',
                'status' => 'draft',
                'priority' => 50,
                'is_system' => false,
                'is_template' => true,
            ],
        ];

        foreach ($templates as $template) {
            AutomationRule::updateOrCreate(
                ['slug' => $template['slug']],
                array_merge($template, [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ])
            );
        }

        $this->command->info('已创建 ' . count($templates) . ' 条自动化规则系统模板');
    }
}
