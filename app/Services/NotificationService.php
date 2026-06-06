<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * 发送通知给用户
     *
     * @param array|int $userIds 用户ID或ID数组
     * @param string $type 通知类型
     * @param string $title 标题
     * @param string $content 内容
     * @param array|null $payload 负载数据（如关联的 license_id 等）
     * @param int|null $tenantId 租户ID
     */
    public function send(
        array|int $userIds,
        string $type,
        string $title,
        string $content,
        ?array $payload = null,
        ?int $tenantId = null,
    ): void {
        $userIds = is_array($userIds) ? $userIds : [$userIds];

        foreach ($userIds as $userId) {
            try {
                $data = [
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title,
                    'content' => $content,
                    'payload' => $payload,
                    'is_read' => false,
                ];

                if ($tenantId) {
                    $data['tenant_id'] = $tenantId;
                } else {
                    $user = User::find($userId);
                    if ($user) {
                        $data['tenant_id'] = $user->tenant_id;
                    }
                }

                Notification::create($data);
            } catch (\Exception $e) {
                Log::error("Failed to send notification to user {$userId}: {$e->getMessage()}");
            }
        }
    }

    /**
     * 发送系统级通知（给租户下所有用户）
     */
    public function sendToTenant(
        int $tenantId,
        string $type,
        string $title,
        string $content,
        ?array $payload = null,
    ): void {
        $userIds = User::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        foreach ($userIds as $userId) {
            try {
                Notification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title,
                    'content' => $content,
                    'payload' => $payload,
                    'is_read' => false,
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send tenant notification: {$e->getMessage()}");
            }
        }
    }

    /**
     * 发送新设备登录通知
     */
    public function sendNewDeviceNotification(
        User $user,
        string $deviceName,
        ?string $ip,
        ?string $userAgent,
    ): void {
        $this->send(
            $user->id,
            'new_device',
            '新设备登录提醒',
            "你的账号刚刚在「{$deviceName}」设备上登录（IP: {$ip}），如果不是你本人的操作，请立即修改密码。",
            [
                'device_name' => $deviceName,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'login_time' => now()->toDateTimeString(),
            ],
            $user->tenant_id,
        );
    }

    /**
     * 发送 License 过期提醒
     */
    public function sendExpiryWarning(int $userId, string $licenseKey, int $daysRemaining): void
    {
        $title = $daysRemaining <= 0
            ? 'License 已过期'
            : "License 即将过期（{$daysRemaining}天）";

        $content = $daysRemaining <= 0
            ? "License {$licenseKey} 已过期，请及时续期以继续使用。"
            : "License {$licenseKey} 将在 {$daysRemaining} 天后过期，请及时续期。";

        $this->send($userId, 'expiry_warning', $title, $content, [
            'license_key' => $licenseKey,
            'days_remaining' => $daysRemaining,
        ]);
    }

    /**
     * 发送 License 状态变更通知
     */
    public function sendStatusChange(int $userId, string $licenseKey, string $oldStatus, string $newStatus): void
    {
        $statusLabels = [
            'active' => '已激活', 'suspended' => '已挂起', 'revoked' => '已撤销',
            'expired' => '已过期', 'frozen' => '已冻结', 'blacklisted' => '已黑名单',
            'refunded' => '已退款',
        ];

        $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
        $newLabel = $statusLabels[$newStatus] ?? $newStatus;

        $this->send($userId, 'status_change',
            "License 状态变更",
            "License {$licenseKey} 状态已从「{$oldLabel}」变更为「{$newLabel}」。",
            ['license_key' => $licenseKey, 'old_status' => $oldStatus, 'new_status' => $newStatus]
        );
    }
}
