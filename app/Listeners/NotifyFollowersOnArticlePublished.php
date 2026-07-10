<?php

namespace App\Listeners;

use App\Events\OaArticlePublished;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\UserConversation;
use App\Models\OaFollower;
use Illuminate\Support\Facades\Log;

class NotifyFollowersOnArticlePublished
{
    public function handle(OaArticlePublished $event): void
    {
        $article = $event->article;
        $account = $article->account;

        if (!$account) return;

        $ownerId = $account->owner_id;
        $followers = OaFollower::where('followable_id', $account->id)
            ->where('followable_type', 'App\\Models\\OfficialAccount')
            ->get();

        foreach ($followers as $follower) {
            try {
                // 查找或创建与关注者的会话
                $conv = UserConversation::where(function($q) use ($ownerId, $follower) {
                        $q->where('user_id', $ownerId)->where('target_user_id', $follower->user_id);
                    })->orWhere(function($q) use ($ownerId, $follower) {
                        $q->where('user_id', $follower->user_id)->where('target_user_id', $ownerId);
                    })->first();

                if (!$conv) {
                    $conv = UserConversation::create([
                        'user_id' => $ownerId,
                        'target_user_id' => $follower->user_id,
                    ]);
                    // 双方加入会话
                    ConversationParticipant::firstOrCreate([
                        'conversation_id' => $conv->id,
                        'user_id' => $ownerId,
                    ]);
                    ConversationParticipant::firstOrCreate([
                        'conversation_id' => $conv->id,
                        'user_id' => $follower->user_id,
                    ]);
                }

                // 发送通知消息
                $title = mb_substr($article->title, 0, 100);
                $content = "📢【{$account->name}】发布了新文章：{$title}\n" . ($article->summary ?: '');

                ConversationMessage::create([
                    'conversation_id' => $conv->id,
                    'user_id' => $ownerId,
                    'content' => $content,
                    'type' => 'text',
                    'metadata' => [
                        'type' => 'oa_article_push',
                        'article_id' => $article->id,
                        'account_id' => $account->id,
                        'account_name' => $account->name,
                        'article_title' => $article->title,
                    ],
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to notify follower on article publish: ' . $e->getMessage());
            }
        }
    }
}
