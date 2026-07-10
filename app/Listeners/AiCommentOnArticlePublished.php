<?php

namespace App\Listeners;

use App\Events\OaArticlePublished;
use App\Models\AiFriendProfile;
use App\Models\OaComment;
use App\Services\AiFriendOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AiCommentOnArticlePublished implements ShouldQueue
{
    public function __construct(
        private AiFriendOrchestrator $orchestrator
    ) {}

    public function handle(OaArticlePublished $event): void
    {
        $article = $event->article;

        // 查找启用了 AI 评论员的互物号配置的 AI 好友
        $aiFriends = AiFriendProfile::where('category', 'commentator')
            ->with('llmConfig')
            ->whereHas('llmConfig')
            ->get();

        foreach ($aiFriends as $aiFriend) {
            try {
                $result = $this->orchestrator
                    ->forFriend($aiFriend)
                    ->generate(null, "请对以下文章写一条有深度、有价值的评论，展现专业见解。\n\n标题：{$article->title}\n\n内容：{$article->content}");

                $reply = $result['content'] ?? '';

                if (!empty($reply)) {
                    OaComment::create([
                        'article_id' => $article->id,
                        'user_id' => $aiFriend->user_id,
                        'content' => mb_substr($reply, 0, 1000),
                        'status' => 'approved',
                    ]);

                    Log::info('[AiCommentator] 已自动评论文章 #' . $article->id);
                }
            } catch (\Throwable $e) {
                Log::error('[AiCommentator] 评论失败: ' . $e->getMessage());
            }
        }
    }
}
