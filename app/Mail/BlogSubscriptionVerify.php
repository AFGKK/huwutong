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
            subject: __('app.mail.blog_subscription_verify_subject'),
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
                    'blog' => __('app.mail.blog_type'),
                    'changelog' => __('app.mail.changelog_type'),
                    'release_note' => __('app.mail.release_note_type'),
                    default => $t,
                }, $this->subscription->subscribed_types ?? ['blog'])),
            ],
        );
    }
}
