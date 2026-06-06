<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '验证您的邮箱 - HWT License',
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
        $name = $this->user->name;
        $code = $this->code;

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px; background: #f5f5f5;">
    <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 32px;">
        <h2 style="margin-top: 0; color: #1a1a1a;">验证您的邮箱</h2>
        <p style="color: #666; line-height: 1.6;">您好，{$name}：</p>
        <p style="color: #666; line-height: 1.6;">感谢您使用 HWT License。请使用以下验证码完成邮箱验证：</p>
        <div style="text-align: center; margin: 32px 0;">
            <span style="font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #409eff; background: #ecf5ff; padding: 12px 24px; border-radius: 4px;">{$code}</span>
        </div>
        <p style="color: #999; font-size: 13px;">验证码有效期为 10 分钟，请勿泄露给他人。</p>
        <p style="color: #999; font-size: 13px;">如果您没有进行此操作，请忽略此邮件。</p>
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
