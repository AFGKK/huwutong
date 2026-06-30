<?php

namespace App\Listeners;

use App\Events\ChannelMessageSent;
use App\Models\AiFriendProfile;
use App\Models\ChannelMember;
use App\Services\AiFriendOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AiModerateChannelMessage implements ShouldQueue
{
    public function __construct(private AiFriendOrchestrator $orchestrator) {}

    public function handle(ChannelMessageSent $event): void
    {
        $msg = $event->message;
        $channelId = $msg->channel_id;

        // 查找该频道的 AI 主持人（通过 ChannelMember 关联）
        $moderatorUserIds = ChannelMember::where('channel_id', $channelId)->pluck('user_id');
        $moderators = AiFriendProfile::where('category', 'moderator')
            ->whereHas('llmConfig')
            ->whereIn('user_id', $moderatorUserIds)
            ->get();

        foreach ($moderators as $mod) {
            try {
                $result = $this->orchestrator->forFriend($mod)->generate(null,
                    "你是一个群聊主持人。请判断以下消息是否包含违规内容（广告、辱骂、刷屏、政治敏感等）。如果违规，回复「违规:原因」，否则回复「通过」。\n\n消息：{$msg->content}");

                $reply = $result['content'] ?? '';

                if (str_starts_with($reply, '违规')) {
                    // 自动删除违规消息
                    $msg->delete();
                    Log::info("[AiModerator] 已删除频道 #{$channelId} 的违规消息: {$reply}");
                }
            } catch (\Throwable $e) {
                Log::error("[AiModerator] 检查失败: " . $e->getMessage());
            }
        }
    }
}
