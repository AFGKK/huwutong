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
            ->subject(__('app.notifications.webhook.subject'))
            ->greeting(__('app.notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.webhook.line_fail', ['name' => $this->endpoint->name]))
            ->line(__('app.notifications.webhook.url', ['url' => $this->endpoint->url]))
            ->line(__('app.notifications.webhook.event', ['type' => $this->eventType]))
            ->line(__('app.notifications.webhook.attempts', ['n' => $this->attempts]))
            ->line(__('app.notifications.webhook.error', ['error' => $this->error]))
            ->line(__('app.notifications.webhook.check'))
            ->action(
                __('app.notifications.webhook.view'),
                url('/webhook-endpoints/' . $this->endpoint->id)
            );
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
            'message' => __('app.notifications.webhook.db_message', [
                'name' => $this->endpoint->name,
                'type' => $this->eventType,
            ]),
        ];
    }
}
