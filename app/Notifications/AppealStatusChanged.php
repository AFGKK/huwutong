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
        return $notifiable->email ? ['mail', 'database'] : ['database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $status = $this->appeal->status === 'approved' ? '已通过' : '未通过';
        $mail = (new MailMessage)
            ->subject("账号申诉{$status}")
            ->greeting("您好，{$notifiable->name}")
            ->line("您于 {$this->appeal->appealed_at->format('Y-m-d H:i')} 提交的账号申诉已被{$status}。");

        if ($this->appeal->status === 'approved') {
            $mail->line('您的账号已恢复正常使用，请重新登录。');
            $mail->action('立即登录', url('/login'));
        } else {
            $mail->line("审核意见：{$this->appeal->review_comment}");
            $mail->line('如有疑问，请联系平台客服。');
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
