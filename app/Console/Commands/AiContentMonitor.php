<?php

namespace App\Console\Commands;

use App\Models\ConversationMessage;
use App\Models\ChannelMessage;
use App\Models\OaComment;
use App\Models\ForumPost;
use App\Models\AiFriendProfile;
use App\Services\AiFriendOrchestrator;
use Illuminate\Console\Command;

class AiContentMonitor extends Command
{
    protected $signature = 'ai:monitor-content {--limit=50 : 每次检查数量}';
    protected $description = 'AI 管理员：扫描 IM 所有内容，自动处理违规内容';

    public function handle(AiFriendOrchestrator $orchestrator): int
    {
        $limit = (int) $this->option('limit');

        $admin = AiFriendProfile::where('category', 'moderator')
            ->with('llmConfig')
            ->whereHas('llmConfig')
            ->first();

        if (!$admin) {
            $this->warn('请先创建一个类别为 moderator 的 AI 管理员好友');
            return Command::SUCCESS;
        }

        $totalRemoved = 0;

        // 1. 检查最近私聊消息
        $this->info('检查私聊消息...');
        $messages = ConversationMessage::where('message_type', 'text')
            ->whereNotNull('content')
            ->latest()
            ->take($limit)
            ->get();

        foreach ($messages as $msg) {
            if ($this->isViolation($orchestrator, $admin, $msg->content)) {
                $msg->update(['content' => '[因违规已被 AI 管理员自动删除]']);
                $totalRemoved++;
                $this->warn("  删除私聊消息 #{$msg->id}");
            }
        }

        // 2. 检查频道消息
        $this->info('检查频道消息...');
        $channelMsgs = ChannelMessage::whereNotNull('content')
            ->latest()
            ->take($limit)
            ->get();

        foreach ($channelMsgs as $msg) {
            if ($this->isViolation($orchestrator, $admin, $msg->content)) {
                $msg->delete();
                $totalRemoved++;
                $this->warn("  删除频道消息 #{$msg->id}");
            }
        }

        // 3. 检查广场帖子
        $this->info('检查广场帖子...');
        $posts = ForumPost::whereNotNull('content')
            ->latest()
            ->take($limit)
            ->get();

        foreach ($posts as $post) {
            if ($this->isViolation($orchestrator, $admin, $post->content)) {
                $post->delete();
                $totalRemoved++;
                $this->warn("  删除广场帖子 #{$post->id}");
            }
        }

        // 4. 检查 OA 评论
        $this->info('检查 OA 评论...');
        $comments = OaComment::whereNotNull('content')
            ->latest()
            ->take($limit)
            ->get();

        foreach ($comments as $comment) {
            if ($this->isViolation($orchestrator, $admin, $comment->content)) {
                $comment->delete();
                $totalRemoved++;
                $this->warn("  删除 OA 评论 #{$comment->id}");
            }
        }

        $this->info("本次巡检共处理 {$totalRemoved} 条违规内容");
        return Command::SUCCESS;
    }

    private function isViolation(AiFriendOrchestrator $orchestrator, AiFriendProfile $admin, string $content): bool
    {
        if (empty(trim($content))) return false;

        try {
            $result = $orchestrator->forFriend($admin)->generate(null,
                "你是一个内容审核管理员。判断以下内容是否违规（广告、色情、政治敏感、辱骂、诈骗）。只回复「违规」或「通过」。\n\n{$content}");

            return str_contains($result['content'] ?? '', '违规');
        } catch (\Throwable $e) {
            $this->error("AI 判断失败: " . $e->getMessage());
            return false;
        }
    }
}
