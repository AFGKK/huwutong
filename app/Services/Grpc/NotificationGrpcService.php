<?php

namespace App\Services\Grpc;

/**
 * Notification gRPC 服务客户端
 */
class NotificationGrpcService extends GrpcService
{
    protected string $serviceName = 'notification';

    protected function getConfigKey(): string
    {
        return 'notification_service';
    }

    public function send(array $userIds, string $type, string $title, string $body, string $channel = 'in_app'): array
    {
        return $this->call(__FUNCTION__, [
            'user_ids' => $userIds,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'channel' => $channel,
        ]);
    }

    public function sendBatch(array $notifications): array
    {
        return $this->call(__FUNCTION__, ['notifications' => $notifications]);
    }

    public function getHistory(int $userId, array $filters = []): array
    {
        return $this->call(__FUNCTION__, array_merge(['user_id' => $userId], $filters));
    }

    public function markAsRead(int $userId, array $notificationIds = []): array
    {
        return $this->call(__FUNCTION__, [
            'user_id' => $userId,
            'notification_ids' => $notificationIds,
            'mark_all' => empty($notificationIds),
        ]);
    }

    public function getUnreadCount(int $userId): array
    {
        return $this->call(__FUNCTION__, ['user_id' => $userId]);
    }

    public function pushWebhook(string $eventType, string $payload, string $targetUrl): array
    {
        return $this->call(__FUNCTION__, [
            'event_type' => $eventType,
            'payload_json' => $payload,
            'target_url' => $targetUrl,
        ]);
    }

    public function sendEmail(array $to, string $subject, string $body, string $templateName = ''): array
    {
        return $this->call(__FUNCTION__, [
            'to' => $to,
            'subject' => $subject,
            'body_html' => $body,
            'template_name' => $templateName,
        ]);
    }
}
