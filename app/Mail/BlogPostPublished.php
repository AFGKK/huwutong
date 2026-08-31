<?php

namespace App\Mail;

use App\Models\BlogPost;
use App\Models\BlogSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BlogPostPublished extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BlogPost $post,
        public BlogSubscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = match ($this->post->type) {
            'changelog' => __('app.mail.blog_update_log'),
            'release_note' => __('app.mail.blog_release_note'),
            default => __('app.mail.blog_default'),
        };

        return new Envelope(
            subject: "{$prefix} {$this->post->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.blog-published',
            with: [
                'post' => $this->post,
                'unsubscribeUrl' => url("/api/blog/subscriptions/unsubscribe/{$this->subscription->token}"),
            ],
        );
    }
}
