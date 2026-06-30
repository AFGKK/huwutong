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
            'changelog' => '[更新日志]',
            'release_note' => '[发布说明]',
            default => '[博客]',
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
