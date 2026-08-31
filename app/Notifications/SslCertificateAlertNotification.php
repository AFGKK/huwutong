<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SslCertificateAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $timeout = 15;

    /**
     * @param string $domain
     * @param string $expiresAt 到期时间
     * @param int $daysLeft 剩余天数
     * @param string $action 操作: expiring_soon, renewed, renew_failed
     */
    public function __construct(
        protected string $domain,
        protected string $expiresAt,
        protected int $daysLeft,
        protected string $action,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (in_array($this->action, ['expiring_soon', 'renew_failed'])) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->action) {
            'expiring_soon' => $this->expiringSoonMail($notifiable),
            'renewed' => $this->renewedMail($notifiable),
            'renew_failed' => $this->renewFailedMail($notifiable),
            default => $this->defaultMail($notifiable),
        };
    }

    protected function expiringSoonMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.notifications.ssl.subject_expiring'))
            ->greeting(__('app.notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.ssl.line_expiring', ['domain' => $this->domain]))
            ->line(__('app.notifications.ssl.expires_at', ['date' => $this->expiresAt]))
            ->line(__('app.notifications.ssl.days_left', ['days' => $this->daysLeft]))
            ->line(__('app.notifications.ssl.renew_hint'));
    }

    protected function renewedMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.notifications.ssl.subject_renewed'))
            ->greeting(__('app.notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.ssl.line_renewed', ['domain' => $this->domain]))
            ->line(__('app.notifications.ssl.new_expires', ['date' => $this->expiresAt]));
    }

    protected function renewFailedMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.notifications.ssl.subject_failed'))
            ->greeting(__('app.notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.ssl.line_failed', ['domain' => $this->domain]))
            ->line(__('app.notifications.ssl.manual_check'))
            ->action(__('app.notifications.ssl.manage_domains'), url('/domains'));
    }

    protected function defaultMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.notifications.ssl.subject_default'))
            ->greeting(__('app.notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.ssl.line_default', ['domain' => $this->domain]))
            ->line(__('app.notifications.ssl.expires_at', ['date' => $this->expiresAt]));
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            'expiring_soon' => __('app.notifications.ssl.db_expiring', [
                'domain' => $this->domain,
                'days' => $this->daysLeft,
            ]),
            'renewed' => __('app.notifications.ssl.db_renewed', ['domain' => $this->domain]),
            'renew_failed' => __('app.notifications.ssl.db_failed', ['domain' => $this->domain]),
        ];

        return [
            'type' => 'ssl_certificate_alert',
            'action' => $this->action,
            'domain' => $this->domain,
            'expires_at' => $this->expiresAt,
            'days_left' => $this->daysLeft,
            'message' => $messages[$this->action] ?? __('app.notifications.ssl.db_default', [
                'domain' => $this->domain,
            ]),
        ];
    }
}
