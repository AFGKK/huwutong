<?php

namespace App\Listeners;

use App\Events\OaArticlePublished;
use App\Models\OaFollower;
use App\Services\UserChatConversationService;
use Illuminate\Support\Facades\Log;

class NotifyFollowersOnArticlePublished
{
    public function __construct(
        protected UserChatConversationService $chatConversations,
    ) {}

    public function handle(OaArticlePublished $event): void
    {
        $article = $event->article;
        $account = $article->account;

        if (! $account) {
            return;
        }

        $ownerId = (int) $account->owner_id;
        if (! $ownerId) {
            return;
        }

        $followers = OaFollower::whereAccountId($account->id)->get();
        $title = mb_substr($article->title, 0, 100);
        $content = "📢【{$account->name}】发布了新文章：{$title}\n" . mb_substr(strip_tags((string) ($article->summary ?: $article->content)), 0, 200);

        foreach ($followers as $follower) {
            if ((int) $follower->user_id === $ownerId) {
                continue;
            }

            try {
                $conv = $this->chatConversations->findOrCreatePrivateConversation($ownerId, (int) $follower->user_id);

                $this->chatConversations->pushTextMessage(
                    $conv,
                    $ownerId,
                    $content,
                    [
                        'type' => 'oa_article_push',
                        'article_id' => $article->id,
                        'account_id' => $account->id,
                        'account_name' => $account->name,
                        'article_title' => $article->title,
                    ],
                    'oa-push-' . $article->id . '-' . $follower->user_id
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to notify follower on article publish: ' . $e->getMessage(), [
                    'article_id' => $article->id,
                    'follower_id' => $follower->user_id,
                ]);
            }
        }
    }
}
