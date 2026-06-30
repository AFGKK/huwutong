<?php

namespace App\Events;

use App\Models\OaArticle;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OaArticlePublished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public OaArticle $article;

    public function __construct(OaArticle $article)
    {
        $this->article = $article;
    }
}
