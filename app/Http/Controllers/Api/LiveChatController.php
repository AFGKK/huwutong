<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HandoffRequest;
use App\Models\LiveChatConversation;
use App\Services\HandoffService;
use App\Services\LiveChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 在线客服 Live Chat 控制器 (M2-103)
 */
class LiveChatController extends Controller
{
    public function __construct(
        protected LiveChatService $liveChat,
        protected HandoffService $handoffService,
    ) {}

    /**
     * 创建会话
     * POST /api/live-chat/conversations
     */
    public function createConversation(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id', 1);
        $user = auth()->user();
        $userId = $user?->id;
        $tenantId = $user?->tenant_id ?? $request->input('tenant_id', 1);

        $conversation = $this->liveChat->createConversation($tenantId, $userId, $request->only(['source', 'department']));
        return ApiResponse::created($conversation, '会话已创建');
    }

    /**
     * 发送消息
     * POST /api/live-chat/conversations/{conversation}/messages
     */
    public function sendMessage(Request $request, LiveChatConversation $conversation): JsonResponse
    {
        $validated = $request->validate(['content' => 'required|string|max:2000']);
        $result = $this->liveChat->sendMessage($conversation, $validated['content']);

        return ApiResponse::success([
            'message' => $result['message'],
            'reply' => $result['reply'],
            'handoff' => $result['handoff'],
        ]);
    }

    /**
     * 获取消息历史
     * GET /api/live-chat/conversations/{conversation}/messages
     */
    public function getMessages(LiveChatConversation $conversation): JsonResponse
    {
        return ApiResponse::success($this->liveChat->getMessages($conversation->id));
    }

    /**
     * 关闭会话
     * POST /api/live-chat/conversations/{conversation}/close
     */
    public function closeConversation(Request $request, LiveChatConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $conversation = $this->liveChat->closeConversation($conversation->id, $validated['rating'] ?? null, $validated['comment'] ?? null);
        return ApiResponse::success($conversation, '会话已关闭');
    }

    // ═══════ 管理端 API ═══════

    /**
     * 仪表盘
     * GET /api/admin/live-chat/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success($this->liveChat->getDashboard($tenantId));
    }

    /**
     * 会话列表
     * GET /api/admin/live-chat/conversations
     */
    public function conversations(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['status', 'assigned_to']);
        return ApiResponse::success($this->liveChat->getConversations($tenantId, $filters));
    }

    /**
     * 客服接单
     * POST /api/admin/live-chat/handoffs/{handoff}/accept
     */
    public function acceptHandoff(int $handoffId): JsonResponse
    {
        $handoff = HandoffRequest::findOrFail($handoffId);
        $this->handoffService->acceptLiveChatHandoff($handoff, auth()->user());
        return ApiResponse::success($handoff->fresh(), '已接单');
    }

    /**
     * 待处理的转人工
     * GET /api/admin/live-chat/pending-handoffs
     */
    public function pendingHandoffs(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $handoffs = $this->handoffService->getLiveChatPendingHandoffs($tenantId);
        return ApiResponse::success($handoffs);
    }
}
