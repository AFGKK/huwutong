<?php

namespace App\Services;

use App\Models\NotificationDeliveryLog;
use App\Models\ScheduledNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 批量通知定时发送服务 (M2-114)
 */
class ScheduledNotificationService
{
    /**
     * 创建定时通知
     */
    public function create(array $data, int $userId): ScheduledNotification
    {
        $data['created_by'] = $userId;
        $data['status'] = $data['scheduled_at'] ? 'scheduled' : 'draft';

        return ScheduledNotification::create($data);
    }

    /**
     * 更新通知
     */
    public function update(ScheduledNotification $notification, array $data): ScheduledNotification
    {
        // 已发送或发送中的不能修改
        if (in_array($notification->status, ['sending', 'sent', 'partial'])) {
            throw new \RuntimeException(__("app.scheduled_notification.sending_notification_cannot_modify"));
        }

        $notification->update($data);

        if (isset($data['scheduled_at']) && $notification->status === 'draft') {
            $notification->update(['status' => 'scheduled']);
        }

        return $notification->fresh();
    }

    /**
     * 发送通知
     */
    public function send(ScheduledNotification $notification): ScheduledNotification
    {
        if ($notification->status === 'sent') {
            throw new \RuntimeException(__("app.scheduled_notification.notification_already_sent"));
        }

        if ($notification->is_cancelled) {
            throw new \RuntimeException(__("app.scheduled_notification.notification_revoked"));
        }

        $notification->update(['status' => 'sending']);

        try {
            // 获取接收人列表
            $recipients = $this->getRecipients($notification);

            if (empty($recipients)) {
                $notification->update([
                    'status' => 'failed',
                    'failure_count' => 0,
                    'sent_at' => now(),
                ]);
                return $notification->fresh();
            }

            $notification->update(['total_recipients' => count($recipients)]);

            $success = 0;
            $failure = 0;

            foreach ($recipients as $recipient) {
                try {
                    $this->deliver($notification, $recipient);
                    $success++;
                } catch (\Exception $e) {
                    $failure++;
                    Log::error("Notification delivery failed for user {$recipient['id']}: {$e->getMessage()}");
                }
            }

            $status = $failure === 0 ? 'sent' : ($success > 0 ? 'partial' : 'failed');

            $notification->update([
                'status' => $status,
                'success_count' => $success,
                'failure_count' => $failure,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $notification->update(['status' => 'failed']);
            Log::error("Notification send failed: {$e->getMessage()}");
        }

        return $notification->fresh();
    }

    /**
     * 投递单条通知
     */
    protected function deliver(ScheduledNotification $notification, array $recipient): void
    {
        $content = $this->replaceVariables($notification->content, $recipient);

        $logData = [
            'notification_id' => $notification->id,
            'user_id' => $recipient['id'],
            'status' => 'sent',
            'sent_at' => now(),
        ];

        if ($notification->channel === 'email') {
            $logData['email'] = $recipient['email'];
            // 实际发送由 Mail Job 处理
            // Mail::to($recipient['email'])->send(...)
        } elseif ($notification->channel === 'in_app') {
            // 站内信: 创建通知记录到 notification_center
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->send($recipient['id'], [
                    'title' => $notification->title,
                    'content' => $content,
                    'type' => 'bulk_notification',
                    'action_url' => $notification->action_url,
                ]);
            } catch (\Exception $e) {
                throw $e;
            }
        } elseif ($notification->channel === 'sms') {
            $logData['phone'] = $recipient['phone'];
            // SMS::send($recipient['phone'], $content)
        }

        NotificationDeliveryLog::create($logData);
    }

    /**
     * 获取接收人列表
     */
    protected function getRecipients(ScheduledNotification $notification): array
    {
        $filters = $notification->filters ?? [];
        $query = User::query();

        if (!empty($filters['role'])) {
            $query->role($filters['role']);
        }
        if (!empty($filters['customer_ids'])) {
            $query->whereIn('customer_id', (array) $filters['customer_ids']);
        }
        if (!empty($filters['user_ids'])) {
            $query->whereIn('id', (array) $filters['user_ids']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }
        if (!empty($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        $maxRecipients = config('scheduled-notification.sending.max_recipients', 10000);
        $users = $query->limit($maxRecipients)->get(['id', 'name', 'email', 'phone']);

        return $users->toArray();
    }

    /**
     * 替换变量
     */
    public function replaceVariables(string $content, array $recipient): string
    {
        $appName = config('app.name', '互物通');

        $replacements = [
            '{user_name}' => $recipient['name'] ?? '用户',
            '{user_email}' => $recipient['email'] ?? '',
            '{app_name}' => $appName,
            '{current_date}' => now()->format('Y-m-d'),
            '{current_time}' => now()->format('Y-m-d H:i'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * 预览通知（变量替换后）
     */
    public function preview(ScheduledNotification $notification): array
    {
        $sampleRecipient = [
            'name' => '示例用户',
            'email' => 'user@example.com',
            'phone' => '138****8888',
        ];

        return [
            'title' => $this->replaceVariables($notification->title, $sampleRecipient),
            'content' => $this->replaceVariables($notification->content, $sampleRecipient),
            'channel' => $notification->channel,
            'recipient_count' => $this->countRecipients($notification),
        ];
    }

    /**
     * 计算接收人数
     */
    public function countRecipients(ScheduledNotification $notification): int
    {
        $filters = $notification->filters ?? [];
        $query = User::query();

        if (!empty($filters['role'])) {
            $query->role($filters['role']);
        }
        if (!empty($filters['user_ids'])) {
            $query->whereIn('id', (array) $filters['user_ids']);
        }

        return $query->count();
    }

    /**
     * 撤销通知
     */
    public function cancel(ScheduledNotification $notification): ScheduledNotification
    {
        if (in_array($notification->status, ['sent', 'cancelled'])) {
            throw new \RuntimeException(__("app.scheduled_notification.cannot_revoke_notification"));
        }

        $cancelWindow = config('scheduled-notification.sending.cancel_window_minutes', 30);

        // 如果已经开始发送，检查是否在可撤销窗口内
        if ($notification->status === 'sending') {
            $elapsed = $notification->sent_at ? $notification->sent_at->diffInMinutes(now()) : 0;
            if ($elapsed > $cancelWindow) {
                throw new \RuntimeException(__("app.scheduled_notification.msg_2a77d38c"));
            }
        }

        $notification->update([
            'is_cancelled' => true,
            'cancelled_at' => now(),
            'status' => 'cancelled',
        ]);

        // 标记待发送的记录为已取消
        NotificationDeliveryLog::where('notification_id', $notification->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        return $notification->fresh();
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(string $startDate, string $endDate): array
    {
        $notifications = ScheduledNotification::whereBetween('created_at', [$startDate, $endDate])->get();

        $totalSent = $notifications->whereIn('status', ['sent', 'partial'])->count();
        $totalScheduled = $notifications->where('status', 'scheduled')->count();
        $totalDraft = $notifications->where('status', 'draft')->count();
        $totalCancelled = $notifications->where('status', 'cancelled')->count();

        // 按类型统计
        $byType = $notifications->groupBy('type')->map(function ($group) {
            return [
                'type' => $group->first()->type,
                'count' => $group->count(),
            ];
        })->values();

        // 按渠道统计
        $byChannel = $notifications->groupBy('channel')->map(function ($group) {
            return [
                'channel' => $group->first()->channel,
                'count' => $group->count(),
            ];
        })->values();

        // 发送成功/失败汇总
        $totalSuccess = $notifications->sum('success_count');
        $totalFailure = $notifications->sum('failure_count');

        return [
            'stats' => [
                'total' => $notifications->count(),
                'sent' => $totalSent,
                'scheduled' => $totalScheduled,
                'draft' => $totalDraft,
                'cancelled' => $totalCancelled,
                'total_recipients' => $notifications->sum('total_recipients'),
                'total_success' => $totalSuccess,
                'total_failure' => $totalFailure,
            ],
            'by_type' => $byType,
            'by_channel' => $byChannel,
        ];
    }

    /**
     * 获取通知列表
     */
    public function getList(array $filters = []): array
    {
        $query = ScheduledNotification::with('creator:id,name,email');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 获取投递日志
     */
    public function getDeliveryLogs(int $notificationId, array $filters = []): array
    {
        $query = NotificationDeliveryLog::where('notification_id', $notificationId)
            ->with('user:id,name,email');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 处理到期的定时通知
     */
    public function processDueSchedules(): array
    {
        $dueNotifications = ScheduledNotification::due()->get();
        $processed = [];

        foreach ($dueNotifications as $notification) {
            try {
                $this->send($notification);
                $processed[] = [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'status' => 'processed',
                ];
            } catch (\Exception $e) {
                $processed[] = [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
                Log::error("Process due notification failed: {$e->getMessage()}");
            }
        }

        return $processed;
    }
}
