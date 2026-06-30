<?php

namespace App\Services;

use App\Models\UserAutoReply;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\UserConversation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserAutoReplyService
{
    /**
     * 检查消息是否需要自动回复，并执行回复
     */
    public function checkAndReply(int $messageId): void
    {
        $msg = ConversationMessage::with('sender')->find($messageId);
        if (!$msg || $msg->message_type !== 'text') return;

        $conv = UserConversation::find($msg->conversation_id);
        if (!$conv || $conv->type !== 'private') return; // 仅私聊自动回复

        // 找到会话中的另一方（即消息接收者）
        $otherParticipant = ConversationParticipant::where('conversation_id', $msg->conversation_id)
            ->where('user_id', '!=', $msg->sender_id)
            ->first();
        if (!$otherParticipant) return;

        $receiverId = $otherParticipant->user_id;

        // 查找接收者的激活自动回复规则
        $rules = UserAutoReply::active()
            ->where('user_id', $receiverId)
            ->orderBy('type')
            ->get();

        if ($rules->isEmpty()) return;

        foreach ($rules as $rule) {
            if (!$rule->isInTimeWindow()) continue;
            if (!$rule->matches($msg->content ?? '')) continue;

            // 发送自动回复
            try {
                ConversationMessage::create([
                    'conversation_id' => $msg->conversation_id,
                    'sender_id' => $receiverId,
                    'message_type' => 'text',
                    'content' => $rule->reply_content,
                    'metadata' => ['auto_reply' => true, 'rule_id' => $rule->id],
                ]);

                $rule->increment('reply_count');
            } catch (\Throwable $e) {
                Log::warning('Auto reply failed', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);
            }

            break; // 只匹配第一条规则
        }
    }

    /**
     * 获取用户当前状态（用于前端展示）
     */
    public function getUserAutoStatus(int $userId): array
    {
        $activeRules = UserAutoReply::active()->where('user_id', $userId)->get();
        $status = 'online';

        foreach ($activeRules as $rule) {
            if (!$rule->isInTimeWindow()) continue;
            if ($rule->type === 'away') { $status = 'away'; break; }
            if ($rule->type === 'vacation') { $status = 'vacation'; break; }
            if ($rule->type === 'busy') { $status = 'busy'; break; }
        }

        return [
            'status' => $status,
            'has_auto_reply' => $activeRules->isNotEmpty(),
            'reply_count' => $activeRules->sum('reply_count'),
        ];
    }
}
