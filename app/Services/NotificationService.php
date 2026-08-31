<?php

namespace App\Services;

use App\Events\NotificationBroadcast;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * 发送通知给用户（支持实时广播）
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

                $notification = Notification::create($data);

                // 实时广播到用户
                if (config('broadcasting.default') !== 'null') {
                    try {
                        NotificationBroadcast::dispatch($notification, $userId);
                    } catch (\Exception $e) {
                        Log::warning("通知广播失败 (用户 {$userId}): {$e->getMessage()}");
                    }
                }
            } catch (\Exception $e) {
                Log::error("发送通知给用户 {$userId} 失败: {$e->getMessage()}");
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
                $notification = Notification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title,
                    'content' => $content,
                    'payload' => $payload,
                    'is_read' => false,
                ]);

                if (config('broadcasting.default') !== 'null') {
                    try {
                        NotificationBroadcast::dispatch($notification, $userId);
                    } catch (\Exception $e) {
                        Log::warning("租户通知广播失败 (用户 {$userId}): {$e->getMessage()}");
                    }
                }
            } catch (\Exception $e) {
                Log::error("发送租户通知失败: {$e->getMessage()}");
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
            __('app.api.service_notification.new_device_login'),
            __('app.api.service_notification.new_device_body', ['device' => $deviceName, 'ip' => $ip]),
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
            ? __('app.api.service_notification.license_expired')
            : __('app.api.service_notification.license_expiring', ['days' => $daysRemaining]);

        $content = $daysRemaining <= 0
            ? __('app.api.service_notification.license_expired_body', ['key' => $licenseKey])
            : __('app.api.service_notification.license_expiring_body', ['key' => $licenseKey, 'days' => $daysRemaining]);

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
            'active' => __('app.api.service_notification.status_active'), 'suspended' => __('app.api.service_notification.status_suspended'), 'revoked' => __('app.api.service_notification.status_revoked'),
            'expired' => '已过期', 'frozen' => __('app.api.service_notification.status_frozen'), 'blacklisted' => __('app.api.service_notification.status_blacklisted'),
            'refunded' => __('app.api.service_notification.status_refunded'),
        ];

        $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
        $newLabel = $statusLabels[$newStatus] ?? $newStatus;

        $this->send($userId, 'status_change',
            __('app.api.service_notification.license_status_changed'),
            __('app.api.service_notification.license_status_body', ['key' => $licenseKey, 'old' => $oldLabel, 'new' => $newLabel]),
            ['license_key' => $licenseKey, 'old_status' => $oldStatus, 'new_status' => $newStatus]
        );
    }

    /**
     * 发送可聚合通知（同 group_key 在窗口内合并 actors/count，抖音式「收到的赞 / 新增关注」）
     *
     * @param  callable(int $count, array $actors): array{0: string, 1: string}  $buildCopy  返回 [title, content]
     */
    public function sendAggregated(
        int $userId,
        string $type,
        string $groupKey,
        callable $buildCopy,
        array $payload = [],
        ?int $tenantId = null,
        int $windowHours = 24,
    ): ?Notification {
        try {
            if (! $tenantId) {
                $user = User::find($userId);
                $tenantId = $user?->tenant_id;
            }

            if (! $tenantId) {
                Log::warning("聚合通知跳过：用户 {$userId} 无 tenant_id");

                return null;
            }

            $existing = Notification::where('user_id', $userId)
                ->where('type', $type)
                ->where('group_key', $groupKey)
                ->where('created_at', '>=', now()->subHours($windowHours))
                ->orderByDesc('id')
                ->first();

            $actor = $payload['actor'] ?? null;
            $actors = [];
            $count = 1;

            if ($existing) {
                $oldPayload = $existing->payload ?? [];
                $actors = is_array($oldPayload['actors'] ?? null) ? $oldPayload['actors'] : [];
                $count = max(1, (int) ($oldPayload['count'] ?? 1));

                if (is_array($actor) && ! empty($actor['id'])) {
                    $actorId = (int) $actor['id'];
                    $already = collect($actors)->contains(fn ($a) => (int) ($a['id'] ?? 0) === $actorId);
                    if (! $already) {
                        array_unshift($actors, $actor);
                        $actors = array_slice($actors, 0, 20);
                        $count++;
                    }
                } else {
                    $count++;
                }

                [$title, $content] = $buildCopy($count, $actors);
                $merged = array_merge($oldPayload, $payload, [
                    'actors' => $actors,
                    'count' => $count,
                    'group_key' => $groupKey,
                ]);

                $existing->update([
                    'title' => $title,
                    'content' => $content,
                    'payload' => $merged,
                    'is_read' => false,
                    'read_at' => null,
                ]);

                $notification = $existing->fresh();
            } else {
                if (is_array($actor)) {
                    $actors = [$actor];
                }
                [$title, $content] = $buildCopy($count, $actors);
                $payload = array_merge($payload, [
                    'actors' => $actors,
                    'count' => $count,
                    'group_key' => $groupKey,
                ]);

                $data = [
                    'user_id' => $userId,
                    'type' => $type,
                    'group_key' => $groupKey,
                    'title' => $title,
                    'content' => $content,
                    'payload' => $payload,
                    'is_read' => false,
                    'tenant_id' => $tenantId,
                ];

                $notification = Notification::create($data);
            }

            if ($notification && config('broadcasting.default') !== 'null') {
                try {
                    NotificationBroadcast::dispatch($notification, $userId);
                } catch (\Exception $e) {
                    Log::warning("聚合通知广播失败 (用户 {$userId}): {$e->getMessage()}");
                }
            }

            return $notification;
        } catch (\Exception $e) {
            Log::error("发送聚合通知给用户 {$userId} 失败: {$e->getMessage()}");

            return null;
        }
    }
}
