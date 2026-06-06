<?php

namespace App\Notifications;

use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WebhookFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $timeout = 15;

    /**
     * @param WebhookEndpoint $endpoint
     * @param string $eventType
     * @param string $error
     * @param int $attempts
     */
    public function __construct(
        protected WebhookEndpoint $endpoint,
        protected string $eventType,
        protected string $error,
        protected int $attempts,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【告警】Webhook 派发失败')
            ->greeting("您好，{$notifiable->name}")
            ->line("您的 Webhook 端点 {$this->endpoint->name} 派发失败")
            ->line("端点 URL：{$this->endpoint->url}")
            ->line("事件类型：{$this->eventType}")
            ->line("失败次数：{$this->attempts}")
            ->line("错误信息：{$this->error}")
            ->line('请检查端点是否正常。')
            ->action('查看 Webhook 端点', url('/webhook-endpoints/' . $this->endpoint->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'webhook_failed',
            'endpoint_id' => $this->endpoint->id,
            'endpoint_name' => $this->endpoint->name,
            'url' => $this->endpoint->url,
            'event_type' => $this->eventType,
            'attempts' => $this->attempts,
            'error' => $this->error,
            'message' => "Webhook {$this->endpoint->name} 派发失败（{$this->eventType}）",
        ];
    }
}
