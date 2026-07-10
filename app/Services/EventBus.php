<?php

namespace App\Services;

use App\Events\LicenseAboutToExpire;
use App\Events\LicenseStatusChanged;
use App\Models\License;
use App\Models\Log;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * 统一事件总线
 *
 * 负责将业务事件分发到多个渠道：
 * - 通知中心（站内信）
 * - Webhook
 * - IM（Slack/飞书/钉钉/企微）
 * - 审计日志
 *
 * 使用方式：
 *   app(EventBus::class)->dispatch($event);
 */
class EventBus
{
    public function __construct(
        protected ?WebhookService $webhookService = null,
    ) {
        $this->webhookService ??= app(WebhookService::class);
    }
    /**
     * 分发事件到所有已注册的渠道
     */
    public function dispatch(object $event): void
    {
        match (true) {
            $event instanceof LicenseStatusChanged => $this->handleStatusChanged($event),
            $event instanceof LicenseAboutToExpire => $this->handleAboutToExpire($event),
            default => null,
        };
    }

    /**
     * 处理 License 状态变更事件
     */
    protected function handleStatusChanged(LicenseStatusChanged $event): void
    {
        $this->logAudit($event);
        $this->dispatchToWebhook($event);
        $this->dispatchToNotificationCenter($event);
    }

    /**
     * 处理即将过期事件
     */
    protected function handleAboutToExpire(LicenseAboutToExpire $event): void
    {
        $this->dispatchToNotificationCenter($event);
        $this->sendExpiryAlert($event);
    }

    /**
     * 记录审计日志（通过 AuditService）
     */
    protected function logAudit(LicenseStatusChanged $event): void
    {
        try {
            app(\App\Services\AuditService::class)->licenseStatusChanged(
                tenantId: $event->license->tenant_id,
                licenseId: $event->license->id,
                licenseKey: $event->license->license_key,
                oldStatus: $event->oldStatus,
                newStatus: $event->newStatus,
                reason: $event->reason,
                userId: auth()->id(),
            );
        } catch (\Throwable $e) {
            LogFacade::error('审计日志写入失败', [
                'error' => $e->getMessage(),
                'license_id' => $event->license->id,
            ]);
        }
    }

    /**
     * 分发到 Webhook
     *
     * 查询该租户下已订阅对应事件的 Webhook 端点并推送
     */
    protected function dispatchToWebhook(LicenseStatusChanged $event): void
    {
        try {
            $eventType = 'license.' . $event->newStatus;
            $payload = [
                'license_key' => $event->license->license_key,
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'reason' => $event->reason,
                'seats' => $event->license->seats,
                'max_devices' => $event->license->max_devices,
            ];

            $this->webhookService->dispatch(
                $event->license->tenant_id,
                $eventType,
                $payload,
                ['license_id' => $event->license->id],
            );
        } catch (\Throwable $e) {
            LogFacade::error('Webhook 分发失败', [
                'error' => $e->getMessage(),
                'license_id' => $event->license->id,
            ]);
        }
    }

    /**
     * 分发到通知中心（站内信）
     */
    protected function dispatchToNotificationCenter(object $event): void
    {
        try {
            $license = $event->license;
            $title = '';
            $content = '';

            if ($event instanceof LicenseStatusChanged) {
                $title = "License 状态变更";
                $content = sprintf(
                    'License [%s] 状态已从「%s」变更为「%s」',
                    $license->license_key,
                    $event->oldStatus,
                    $event->newStatus,
                );
            } elseif ($event instanceof LicenseAboutToExpire) {
                $title = "License 即将过期";
                $content = sprintf(
                    'License [%s] 将在 %d 天后过期（%s），请及时续费',
                    $license->license_key,
                    $event->daysRemaining,
                    $license->expires_at->format('Y-m-d'),
                );
            }

            if ($title && $content) {
                $payload = [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                ];

                if ($event instanceof LicenseStatusChanged) {
                    $payload['old_status'] = $event->oldStatus;
                    $payload['new_status'] = $event->newStatus;
                } elseif ($event instanceof LicenseAboutToExpire) {
                    $payload['days_remaining'] = $event->daysRemaining;
                }

                \App\Models\Notification::create([
                    'tenant_id' => $license->tenant_id,
                    'customer_id' => $license->customer_id,
                    'type' => $event instanceof LicenseAboutToExpire ? 'expiry_warning' : 'status_change',
                    'title' => $title,
                    'content' => $content,
                    'payload' => $payload,
                ]);
            }
        } catch (\Throwable $e) {
            LogFacade::error('通知中心分发失败', [
                'error' => $e->getMessage(),
                'license_id' => $event->license->id ?? null,
            ]);
        }
    }

    /**
     * 发送过期提醒（可扩展为邮件/短信）
     */
    protected function sendExpiryAlert(LicenseAboutToExpire $event): void
    {
        // 预留邮件/短信发送逻辑
        LogFacade::info('过期提醒', [
            'license_key' => $event->license->license_key,
            'days_remaining' => $event->daysRemaining,
            'expires_at' => $event->license->expires_at->format('Y-m-d'),
        ]);
    }
}
