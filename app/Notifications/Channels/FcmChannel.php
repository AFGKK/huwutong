<?php

namespace App\Notifications\Channels;

use App\Services\FcmPushService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * D-28: FCM 推送通知通道
 *
 * 在 Notification 的 via() 返回 ['fcm'] 即可使用此通道。
 *
 * Notification 需要实现 toFcm($notifiable) 方法，
 * 返回 ['title' => '...', 'body' => '...', 'data' => [...]]
 */
class FcmChannel
{
    public function __construct(
        protected FcmPushService $fcm,
    ) {}

    /**
     * 发送通知到 FCM
     */
    public function send(object $notifiable, Notification $notification): void
    {
        // 只支持 User notifiable
        if (!method_exists($notifiable, 'fcm_token') || !$notifiable->fcm_token) {
            return;
        }

        // 检查 Notification 是否有 toFcm 方法
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $payload = $notification->toFcm($notifiable);
        if (!$payload || empty($payload['title'])) {
            return;
        }

        $title = $payload['title'];
        $body = $payload['body'] ?? '';
        $data = $payload['data'] ?? [];

        $result = $this->fcm->sendToUser($notifiable, $title, $body, $data);

        // 如果 token 已失效，清理
        if (!empty($result['should_remove'])) {
            try {
                $notifiable->fcm_token = null;
                $notifiable->fcm_platform = null;
                $notifiable->fcm_device_name = null;
                $notifiable->fcm_token_updated_at = null;
                $notifiable->saveQuietly();

                Log::info('FCM 已清除失效 Token', ['user_id' => $notifiable->id]);
            } catch (\Throwable $e) {
                Log::warning('FCM 清理失效 Token 失败', [
                    'user_id' => $notifiable->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$result['success']) {
            Log::warning('FCM 通知发送失败', [
                'user_id' => $notifiable->id,
                'notification' => get_class($notification),
                'error' => $result['message'],
            ]);
        }
    }
}
