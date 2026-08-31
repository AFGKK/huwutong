<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $content,
        public ?array $payload = null,
        public ?string $userName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title . ' - HWT License',
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
        $greeting = $this->userName ? __('app.mail.greeting_name', ['name' => $this->userName]) : __('app.mail.greeting');
        $actionUrl = $this->payload['action_url'] ?? null;
        $actionText = $this->payload['action_text'] ?? null;

                $autoSendNoReply = __('app.mail.auto_send_no_reply');
return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px; background: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 32px;">
        <h2 style="margin-top: 0; color: #0f172a;">{$this->title}</h2>
        <p style="color: #666; line-height: 1.6;">{$greeting}</p>
        <p style="color: #333; line-height: 1.6;">{$this->content}</p>
HTML
            . ($actionUrl ? <<<HTML
        <p style="margin: 24px 0; text-align: center;">
            <a href="{$actionUrl}" style="display: inline-block; padding: 12px 24px; background: #0f172a; color: #fff; text-decoration: none; border-radius: 4px; font-size: 15px;">{$actionText}</a>
        </p>
HTML
            : '')
            . <<<HTML
        <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
        <p style="color: #999; font-size: 12px;">{$autoSendNoReply}</p>
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
