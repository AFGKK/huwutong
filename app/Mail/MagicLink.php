<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $token,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('app.mail.magic_link.subject', [
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
        $app = config('app.name', 'HWT License');
        $url = e($this->loginUrl);
        $minutes = 10;
        $brand = e($app);
        $heading = e(__('app.mail.magic_link.heading'));
        $body = e(__('app.mail.magic_link.body', ['app' => $app]));
        $cta = e(__('app.mail.magic_link.cta'));
        $expires = e(__('app.mail.magic_link.expires', ['minutes' => $minutes]));
        $copyHint = e(__('app.mail.magic_link.copy_hint'));
        $footer = e(__('app.mail.magic_link.footer', ['app' => $app]));

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background: #f5f5f5;">
    <div style="max-width: 480px; margin: 40px auto; background: #fff; border-radius: 12px; padding: 40px 32px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
        <div style="text-align: center; margin-bottom: 24px;">
            <h2 style="margin: 0; color: #1a1a1a;">{$brand}</h2>
        </div>
        <h3 style="color: #333; margin: 0 0 8px;">{$heading}</h3>
        <p style="color: #666; font-size: 14px; line-height: 1.6; margin: 0 0 24px;">
            {$body}
        </p>
        <div style="text-align: center; margin: 32px 0;">
            <a href="{$url}" style="display: inline-block; padding: 14px 36px; background: #0f172a; color: #fff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 500;">
                {$cta}
            </a>
        </div>
        <p style="color: #999; font-size: 13px; line-height: 1.5; margin: 0;">
            {$expires}<br>
            {$copyHint}<br>
            <span style="color: #0f172a; word-break: break-all; font-size: 12px;">{$url}</span>
        </p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
        <p style="color: #bbb; font-size: 12px; text-align: center; margin: 0;">
            {$footer}
        </p>
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
