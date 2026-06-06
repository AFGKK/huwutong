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
            ->subject('【告警】SSL 证书即将到期')
            ->greeting("您好，{$notifiable->name}")
            ->line("域名 **{$this->domain}** 的 SSL 证书即将到期")
            ->line("到期时间：{$this->expiresAt}")
            ->line("剩余天数：{$this->daysLeft} 天")
            ->line('请及时续期证书以免影响服务。');
    }

    protected function renewedMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SSL 证书已自动续期')
            ->greeting("您好，{$notifiable->name}")
            ->line("域名 **{$this->domain}** 的 SSL 证书已自动续期")
            ->line("新到期时间：{$this->expiresAt}");
    }

    protected function renewFailedMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【紧急】SSL 证书续期失败')
            ->greeting("您好，{$notifiable->name}")
            ->line("域名 **{$this->domain}** 的 SSL 证书续期失败")
            ->line('请手动检查并续期证书。')
            ->action('管理域名', url('/domains'));
    }

    protected function defaultMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SSL 证书通知')
            ->greeting("您好，{$notifiable->name}")
            ->line("域名 **{$this->domain}** SSL 证书状态更新")
            ->line("到期时间：{$this->expiresAt}");
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            'expiring_soon' => "SSL 证书 {$this->domain} 将在 {$this->daysLeft} 天后到期",
            'renewed' => "SSL 证书 {$this->domain} 已自动续期",
            'renew_failed' => "SSL 证书 {$this->domain} 续期失败",
        ];

        return [
            'type' => 'ssl_certificate_alert',
            'action' => $this->action,
            'domain' => $this->domain,
            'expires_at' => $this->expiresAt,
            'days_left' => $this->daysLeft,
            'message' => $messages[$this->action] ?? "SSL 证书 {$this->domain} 状态更新",
        ];
    }
}
