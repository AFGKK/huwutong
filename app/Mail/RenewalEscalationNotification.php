<?php

namespace App\Mail;

use App\Models\RenewalEscalation;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RenewalEscalationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RenewalEscalation $escalation,
        public Subscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '续费失败 — 需要人工介入 - HWT License',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $customerName = $this->subscription->customer->name ?? 'N/A';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px; background: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 32px;">
        <h2 style="margin-top: 0; color: #f56c6c;">⚠️ 续费失败 — 需要人工介入</h2>
        <p style="color: #666; line-height: 1.6;">以下订阅续费失败，需要管理员处理：</p>
        <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
            <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #999;">订阅 ID</td><td style="padding: 8px; border-bottom: 1px solid #eee;">#{$this->subscription->id}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #999;">客户</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{$customerName}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #999;">套餐</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{$this->subscription->plan}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #999;">金额</td><td style="padding: 8px; border-bottom: 1px solid #eee;">¥{$this->subscription->price}/{$this->subscription->billing_period}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #999;">续费失败原因</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{$this->escalation->message}</td></tr>
        </table>
        <p style="color: #999; font-size: 13px;">请登录管理后台处理此问题。</p>
    </div>
</body>
</html>
HTML;
    }

    public function attachments(): array
    {
        return [];
    }
}
