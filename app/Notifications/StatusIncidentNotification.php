<?php

namespace App\Notifications;

use App\Models\StatusIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StatusIncidentNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected StatusIncident $incident,
        protected string $status,
        protected string $unsubscribeToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'HWT');
        $statusLabel = $this->getStatusLabel();
        $unsubscribeUrl = url("/api/status/unsubscribe/{$this->unsubscribeToken}");

        $mail = (new MailMessage)
            ->subject(__('app.notifications.status.subject', [
                'app' => $appName,
                'title' => $this->incident->title,
            ]))
            ->greeting(__('app.notifications.status.greeting', ['app' => $appName]))
            ->line(__('app.notifications.status.incident', ['title' => $this->incident->title]))
            ->line(__('app.notifications.status.status', ['status' => $statusLabel]))
            ->line(__('app.notifications.status.severity', [
                'severity' => $this->incident->severityLabel(),
            ]))
            ->action(__('app.notifications.status.view'), url('/status'));

        if ($this->status === 'resolved') {
            $mail->success();
        } else {
            $mail->warning();
        }

        $mail->line('---')
            ->line(__('app.notifications.status.unsubscribe', ['url' => $unsubscribeUrl]));

        return $mail;
    }

    protected function getStatusLabel(): string
    {
        $key = 'app.notifications.status.' . $this->status;

        return __($key) !== $key ? __($key) : $this->status;
    }
}
