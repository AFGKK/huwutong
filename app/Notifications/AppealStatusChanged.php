<?php

namespace App\Notifications;

use App\Models\UserAppeal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppealStatusChanged extends Notification
{
    use Queueable;

    public function __construct(protected UserAppeal $appeal) {}

    public function via($notifiable): array
    {
        $channels = [];

        if ($notifiable->email) {
            $channels[] = 'mail';
        }

        // D-28: FCM 推送
        if ($notifiable->fcm_token ?? null) {
            $channels[] = 'fcm';
        }

        return $channels;
    }

    /**
     * D-28: FCM 推送消息
     */
    public function toFcm($notifiable): array
    {
        $status = __('app.notifications.appeal.' . ($this->appeal->status === 'approved' ? 'approved' : 'rejected'));

        return [
            'title' => __('app.notifications.appeal.title', ['status' => $status]),
            'body' => __('app.notifications.appeal.body', [
                'time' => $this->appeal->appealed_at->format('Y-m-d H:i'),
                'status' => $status,
            ]),
            'data' => [
                'type' => 'appeal_status',
                'appeal_id' => (string) $this->appeal->id,
                'status' => $this->appeal->status,
                'route' => '/appeals/' . $this->appeal->id,
                'category' => 'account',
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $status = __('app.notifications.appeal.' . ($this->appeal->status === 'approved' ? 'approved' : 'rejected'));
        $mail = (new MailMessage)
            ->subject(__('app.notifications.appeal.title', ['status' => $status]))
            ->greeting(__('app.notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.appeal.body', [
                'time' => $this->appeal->appealed_at->format('Y-m-d H:i'),
                'status' => $status,
            ]));

        if ($this->appeal->status === 'approved') {
            $mail->line(__('app.notifications.appeal.restored'));
            $mail->action(__('app.notifications.appeal.login_now'), url('/login'));
        } else {
            $mail->line(__('app.notifications.appeal.review_comment', [
                'comment' => $this->appeal->review_comment,
            ]));
            $mail->line(__('app.notifications.appeal.contact_support'));
        }

        return $mail;
    }

    public function toArray($notifiable): array
    {
        return [
            'appeal_id' => $this->appeal->id,
            'status' => $this->appeal->status,
            'review_comment' => $this->appeal->review_comment,
            'appealed_at' => $this->appeal->appealed_at?->toDateTimeString(),
            'reviewed_at' => $this->appeal->reviewed_at?->toDateTimeString(),
        ];
    }
}
