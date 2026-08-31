<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 多渠道通知统一编排服务
 *
 * 支持通过站内信、邮件、短信三种渠道发送通知，
 * 并遵循用户的渠道偏好设置。
 */
class MultiChannelNotifier
{
    public function __construct(
        protected NotificationService $notificationService,
        protected SmsService $smsService,
    ) {}

    /**
     * 统一发送通知（自动根据用户偏好选择渠道）
     *
     * @param User|int $user 用户对象或ID
     * @param string $title 标题
     * @param string $content 内容（纯文本）
     * @param string $type 通知类型（用于偏好匹配）
     * @param array|null $payload 附加数据
     * @param array $channels 强制指定渠道（留空则使用用户偏好）
     */
    public function send(
        User|int $user,
        string $title,
        string $content,
        string $type = 'general',
        ?array $payload = null,
        array $channels = [],
    ): void {
        $userId = is_int($user) ? $user : $user->id;
        $userModel = is_int($user) ? User::find($user) : $user;

        if (! $userModel) {
            Log::warning("MultiChannelNotifier: user not found (ID: {$userId})");
            return;
        }

        $channels = $channels ?: $this->resolveUserChannels($userModel, $type);

        foreach ($channels as $channel) {
            try {
                match ($channel) {
                    'database' => $this->sendDatabase($userId, $type, $title, $content, $payload, $userModel->tenant_id),
                    'mail' => $this->sendMail($userModel, $title, $content, $payload),
                    'sms' => $this->sendSms($userModel, $title, $content),
                    default => Log::warning("MultiChannelNotifier: unknown channel {$channel}"),
                };
            } catch (\Throwable $e) {
                Log::error("MultiChannelNotifier: channel {$channel} failed for user {$userId}", [
                    'error' => $e->getMessage(),
                    'type' => $type,
                ]);
            }
        }
    }

    /**
     * 发送续费到期提醒（特殊包装，支持天数参数）
     */
    public function sendRenewalReminder(
        User|int $user,
        string $licenseKey,
        int $daysRemaining,
        ?string $productName = null,
    ): void {
        $userId = is_int($user) ? $user : $user->id;

        $isExpired = $daysRemaining <= 0;
        $title = $isExpired
            ? 'License 已过期'
            : "License 即将过期（{$daysRemaining}天）";

        $content = $isExpired
            ? "您的 License {$licenseKey} 已过期，请尽快续费以免服务中断。"
            : "您的 License {$licenseKey} 将在 {$daysRemaining} 天后过期，请及时续费。";

        $payload = [
            'license_key' => $licenseKey,
            'days_remaining' => $daysRemaining,
            'product_name' => $productName,
        ];

        $this->send($user, $title, $content, 'license_expiry', $payload);
    }

    /**
     * 发送续费失败通知
     */
    public function sendRenewalFailed(
        User|int $user,
        string $planName,
        float $amount,
        int $attemptNumber,
        string $failureReason,
    ): void {
        $title = "续费失败提醒（第{$attemptNumber}次）";
        $content = "您的 {$planName} 套餐续费（¥{$amount}）已失败 {$attemptNumber} 次。"
            . "失败原因：{$failureReason}。系统将在稍后自动重试，您也可以登录后台手动续费。";

        $this->send($user, $title, $content, 'renewal_failed', [
            'plan' => $planName,
            'amount' => $amount,
            'attempt_number' => $attemptNumber,
            'failure_reason' => $failureReason,
        ]);
    }

    /**
     * 发送续费升级通知（需要人工介入）
     */
    public function sendRenewalEscalated(
        User|int $user,
        string $planName,
        int $attemptNumber,
    ): void {
        $title = "续费失败 — 需要人工介入";
        $content = "您的 {$planName} 套餐续费已连续失败 {$attemptNumber} 次，"
            . "我们已通知客服团队处理。请保持联系方式畅通。";

        $this->send($user, $title, $content, 'renewal_escalated', [
            'plan' => $planName,
            'attempt_number' => $attemptNumber,
        ]);
    }

    // ── 各渠道发送实现 ──

    protected function sendDatabase(int $userId, string $type, string $title, string $content, ?array $payload, ?int $tenantId): void
    {
        $this->notificationService->send(
            $userId,
            $type,
            $title,
            $content,
            $payload,
            $tenantId,
        );
    }

    protected function sendMail(User $user, string $title, string $content, ?array $payload = null): void
    {
        if (! $user->email || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            Log::warning("MultiChannelNotifier: invalid email for user {$user->id}");
            return;
        }

        Mail::to($user->email)->send(new \App\Mail\GenericNotification(
            title: $title,
            content: $content,
            payload: $payload,
            userName: $user->name,
        ));
    }

    protected function sendSms(User $user, string $title, string $content): void
    {
        $phone = $user->phone ?? $user->phone_number ?? null;
        if (! $phone) {
            Log::warning("MultiChannelNotifier: no phone for user {$user->id}");
            return;
        }

        // 短信内容需要精简
        $smsContent = substr("{$title}: {$content}", 0, 150);
        $result = $this->smsService->sendNotification($phone, $smsContent);

        if (! ($result['success'] ?? false)) {
            Log::warning("MultiChannelNotifier: sms failed for user {$user->id}", [
                'message' => $result['message'] ?? 'unknown',
            ]);
        }
    }

    /**
     * 解析用户的渠道偏好
     */
    protected function resolveUserChannels(User $user, string $type): array
    {
        // 默认：使用站内信 + 邮件
        $defaults = ['database', 'mail'];

        // 优先从 notification_preferences 读取
        $prefs = $user->notificationPreferences ?? [];

        // 尝试从 relation 获取
        if ($user->relationLoaded('notificationPreference') && $user->notificationPreference) {
            $prefs = $user->notificationPreference->channels ?? [];
        }

        if (! empty($prefs) && isset($prefs[$type])) {
            return (array) $prefs[$type];
        }

        // 紧急类型默认加 SMS
        if (in_array($type, ['renewal_escalated', 'license_expiry'])) {
            return [...$defaults, 'sms'];
        }

        return $defaults;
    }
}
