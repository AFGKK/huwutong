<?php

namespace App\Mail;

use App\Models\BlogSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BlogSubscriptionVerify extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BlogSubscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '确认订阅 HWT License 开发者博客',
        );
    }

    public function content(): Content
    {
        $verifyUrl = url("/api/blog/subscriptions/verify/{$this->subscription->token}");
        $unsubscribeUrl = url("/api/blog/subscriptions/unsubscribe/{$this->subscription->token}");

        return new Content(
            markdown: 'emails.blog-subscription-verify',
            with: [
                'verifyUrl' => $verifyUrl,
                'unsubscribeUrl' => $unsubscribeUrl,
                'types' => implode('、', array_map(fn($t) => match($t) {
                    'blog' => '博客',
                    'changelog' => '更新日志',
                    'release_note' => '发布说明',
                    default => $t,
                }, $this->subscription->subscribed_types ?? ['blog'])),
            ],
        );
    }
}
