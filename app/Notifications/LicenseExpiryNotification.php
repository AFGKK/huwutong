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

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $license = $this->license;
        $isExpired = $this->level === 'expired';

        $subject = $isExpired
            ? '【重要】您的 License 已过期'
            : "【提醒】您的 License 将在 {$this->daysLabel()} 后过期";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("您好，{$notifiable->name}")
            ->line("您的 License **{$license->license_key}** " . ($isExpired ? '已过期' : '即将过期'));

        if (! $isExpired) {
            $message->line("到期时间：{$license->expires_at?->format('Y-m-d')}");
            $message->line("剩余天数：{$this->daysLabel()}");
        }

        $productName = $license->relationLoaded('product') && $license->product ? $license->product->name : 'N/A';
        $message->line("产品：{$productName}");

        if ($isExpired) {
            $message->line('请尽快续费以免服务中断。');
            $message->action('立即续费', url('/licenses/' . $license->id));
        } else {
            $message->line('请确保及时续费。');
            $message->action('查看 License', url('/licenses/' . $license->id));
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'license_expiry',
            'level' => $this->level,
            'license_id' => $this->license->id,
            'license_key' => $this->license->license_key,
            'expires_at' => $this->license->expires_at?->toDateString(),
            'days_left' => now()->diffInDays($this->license->expires_at, false),
            'message' => $this->level === 'expired'
                ? "License {$this->license->license_key} 已过期"
                : "License {$this->license->license_key} 将在 {$this->daysLabel()} 后过期",
        ];
    }

    protected function daysLabel(): string
    {
        return match ($this->level) {
            '7_days' => '7 天',
            '3_days' => '3 天',
            '1_day' => '1 天',
            'expired' => '已过期',
            default => $this->level,
        };
    }
}
