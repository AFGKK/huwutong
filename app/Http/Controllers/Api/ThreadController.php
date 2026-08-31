<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    // ════════════════════════════════════════════
    // THREAD-001~004: Thread 话题系统
    // ════════════════════════════════════════════

    /**
     * 在消息下创建 Thread 回复
     */
    public function reply(int $messageId, Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $parent = ConversationMessage::findOrFail($messageId);
        $myId = auth()->id();

        // 验证用户是会话参与者
        $isParticipant = ConversationParticipant::where('conversation_id', $parent->conversation_id)
            ->where('user_id', $myId)->whereNull('deleted_at')->exists();
        if (!$isParticipant) {
            return ApiResponse::error('FORBIDDEN', __("app.thread.msg_07d83b66"), 403);
        }

        // 如果父消息没有 thread_parent_id，则自己就是 thread 根
        $threadRootId = $parent->thread_parent_id ?? $parent->id;

        $reply = ConversationMessage::create([
            'conversation_id' => $parent->conversation_id,
            'sender_id' => $myId,
            'content' => $request->input('content'),
            'message_type' => 'text',
            'thread_parent_id' => $threadRootId,
            'client_msg_id' => 'thread-' . uniqid(),
        ]);

        // 更新父消息的回复计数
        ConversationMessage::where('id', $threadRootId)->increment('thread_reply_count');

        // 也更新原消息（如果是间接回复）
        if ($parent->id !== $threadRootId) {
            $parent->increment('thread_reply_count');
        }

        return ApiResponse::success(
            $reply->load('sender:id,name'),
            __('app.thread.replied'),
            201
        );
    }

    /**
     * 获取 Thread 回复列表
     */
    public function replies(int $messageId, Request $request): JsonResponse
    {
        $parent = ConversationMessage::findOrFail($messageId);
        $threadRootId = $parent->thread_parent_id ?? $parent->id;

        $perPage = min((int) $request->input('per_page', 50), 100);

        $replies = ConversationMessage::where('thread_parent_id', $threadRootId)
            ->whereNull('deleted_at')
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->paginate($perPage);

        return ApiResponse::paginated($replies);
    }

    /**
     * 获取 Thread 摘要（根消息 + 最近3条回复）
     */
    public function threadSummary(int $messageId): JsonResponse
    {
        $parent = ConversationMessage::with('sender:id,name')->findOrFail($messageId);
        $threadRootId = $parent->thread_parent_id ?? $parent->id;

        $recentReplies = ConversationMessage::where('thread_parent_id', $threadRootId)
            ->whereNull('deleted_at')
            ->with('sender:id,name')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get()
            ->reverse()
            ->values();

        $replyCount = ConversationMessage::where('thread_parent_id', $threadRootId)
            ->whereNull('deleted_at')
            ->count();

        return ApiResponse::success([
            'root_message' => $parent,
            'recent_replies' => $recentReplies,
            'reply_count' => $replyCount,
        ]);
    }
}
