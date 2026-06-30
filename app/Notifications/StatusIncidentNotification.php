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
            ->subject("[{$appName}] 系统状态更新: {$this->incident->title}")
            ->greeting("{$appName} 系统状态更新")
            ->line("事件: {$this->incident->title}")
            ->line("当前状态: {$statusLabel}")
            ->line("严重程度: {$this->incident->severityLabel()}")
            ->action('查看详情', url('/status'));

        if ($this->status === 'resolved') {
            $mail->success();
        } else {
            $mail->warning();
        }

        $mail->line('---')
            ->line("如果您不想继续接收通知，请<a href='{$unsubscribeUrl}'>点击此处退订</a>。");

        return $mail;
    }

    protected function getStatusLabel(): string
    {
        return [
            'investigating' => '调查中',
            'identified' => '已确认',
            'monitoring' => '监控中',
            'resolved' => '已解决',
            'postmortem' => '事后分析',
        ][$this->status] ?? $this->status;
    }
}
