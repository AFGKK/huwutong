<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('app.mail.password_reset.subject', [
                'app' => config('app.name', 'HWT License'),
            ]),
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
        $code = e($this->code);
        $heading = e(__('app.mail.password_reset.heading'));
        $greeting = e(__('app.mail.password_reset.greeting'));
        $body = e(__('app.mail.password_reset.body', [
            'app' => config('app.name', 'HWT License'),
        ]));
        $expires = e(__('app.mail.password_reset.expires'));
        $ignore = e(__('app.mail.password_reset.ignore'));

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px; background: #f5f5f5;">
    <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 32px;">
        <h2 style="margin-top: 0; color: #1a1a1a;">{$heading}</h2>
        <p style="color: #666; line-height: 1.6;">{$greeting}</p>
        <p style="color: #666; line-height: 1.6;">{$body}</p>
        <div style="text-align: center; margin: 32px 0;">
            <span style="font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #0f172a; background: #f1f5f9; padding: 12px 24px; border-radius: 4px;">{$code}</span>
        </div>
        <p style="color: #999; font-size: 13px;">{$expires}</p>
        <p style="color: #999; font-size: 13px;">{$ignore}</p>
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
