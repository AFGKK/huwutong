<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LicenseExpiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $timeout = 15;

    /**
     * @param License $license
     * @param string $level 提醒级别: 7_days, 3_days, 1_day, expired
     */
    public function __construct(
        protected License $license,
        protected string $level,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // 只在重要级别发送邮件
        if (in_array($this->level, ['1_day', 'expired'])) {
            $channels[] = 'mail';
        }

        // D-28: 如果有 FCM Token，通过 FCM 推送
        if ($notifiable->fcm_token ?? null) {
            $channels[] = 'fcm';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $license = $this->license;
        $isExpired = $this->level === 'expired';
        $days = $this->daysLabel();

        $subject = $isExpired
            ? __('app.notifications.license_expiry.subject_expired')
            : __('app.notifications.license_expiry.subject_soon', ['days' => $days]);

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting(__('app.notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('app.notifications.license_expiry.line_key', [
                'key' => $license->license_key,
                'state' => $isExpired
                    ? __('app.notifications.license_expiry.state_expired')
                    : __('app.notifications.license_expiry.state_soon'),
            ]));

        if (! $isExpired) {
            $message->line(__('app.notifications.license_expiry.expires_at', [
                'date' => $license->expires_at?->format('Y-m-d'),
            ]));
            $message->line(__('app.notifications.license_expiry.days_left', ['days' => $days]));
        }

        $productName = $license->relationLoaded('product') && $license->product ? $license->product->name : 'N/A';
        $message->line(__('app.notifications.license_expiry.product', ['name' => $productName]));

        if ($isExpired) {
            $message->line(__('app.notifications.license_expiry.renew_asap'));
            $message->action(__('app.notifications.license_expiry.renew_now'), url('/licenses/' . $license->id));
        } else {
            $message->line(__('app.notifications.license_expiry.renew_hint'));
            $message->action(__('app.notifications.license_expiry.view_license'), url('/licenses/' . $license->id));
        }

        return $message;
    }

    /**
     * D-28: FCM 推送消息
     */
    public function toFcm(object $notifiable): array
    {
        $license = $this->license;
        $isExpired = $this->level === 'expired';
        $days = $this->daysLabel();

        $title = $isExpired
            ? __('app.notifications.license_expiry.fcm_title_expired')
            : __('app.notifications.license_expiry.fcm_title_soon', ['days' => $days]);

        $body = $isExpired
            ? __('app.notifications.license_expiry.fcm_body_expired', ['key' => $license->license_key])
            : __('app.notifications.license_expiry.fcm_body_soon', [
                'key' => $license->license_key,
                'date' => $license->expires_at?->format('Y-m-d'),
            ]);

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'license_expiry',
                'level' => $this->level,
                'license_id' => (string) $license->id,
                'license_key' => $license->license_key,
                'route' => '/licenses/' . $license->id,
                'category' => 'license',
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        $days = $this->daysLabel();

        return [
            'type' => 'license_expiry',
            'level' => $this->level,
            'license_id' => $this->license->id,
            'license_key' => $this->license->license_key,
            'expires_at' => $this->license->expires_at?->toDateString(),
            'days_left' => now()->diffInDays($this->license->expires_at, false),
            'message' => $this->level === 'expired'
                ? __('app.notifications.license_expiry.db_expired', ['key' => $this->license->license_key])
                : __('app.notifications.license_expiry.db_soon', [
                    'key' => $this->license->license_key,
                    'days' => $days,
                ]),
        ];
    }

    protected function daysLabel(): string
    {
        return match ($this->level) {
            '7_days' => __('app.notifications.license_expiry.days_7'),
            '3_days' => __('app.notifications.license_expiry.days_3'),
            '1_day' => __('app.notifications.license_expiry.days_1'),
            'expired' => __('app.notifications.license_expiry.days_expired'),
            default => $this->level,
        };
    }
}
