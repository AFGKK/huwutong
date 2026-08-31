<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $action = 'verify',
        public int $expiresInMinutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        $app = config('app.name', 'HWT License');
        $key = match ($this->action) {
            'register' => 'app.mail.verify_code.subject_register',
            'login' => 'app.mail.verify_code.subject_login',
            'reset_password' => 'app.mail.verify_code.subject_reset',
            'bind' => 'app.mail.verify_code.subject_bind',
            default => 'app.mail.verify_code.subject_default',
        };

        return new Envelope(subject: __($key, ['app' => $app]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-code',
            with: [
                'code' => $this->code,
                'action' => $this->action,
                'expiresIn' => $this->expiresInMinutes,
            ],
        );
    }
}
