<?php

namespace App\Services;

use App\Models\HandoffRequest;
use App\Models\LiveChatConversation;
use App\Models\LiveChatMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 在线客服 Live Chat 服务 (M2-103)
 *
 * AI优先响应→转人工+工单联动
 * 转人工使用统一的 HandoffService 管理
 */
class LiveChatService
{
    public function __construct(
        protected HandoffService $handoffService,
    ) {}
    /**
     * 创建新会话
     */
    public function createConversation(int $tenantId, ?int $userId = null, array $data = []): LiveChatConversation
    {
        return LiveChatConversation::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'session_id' => 'chat_' . Str::random(24),
            'status' => 'active',
            'source' => $data['source'] ?? 'portal',
            'department' => $data['department'] ?? null,
        ]);
    }

    /**
     * 发送消息并获取 AI 回复
     */
    public function sendMessage(LiveChatConversation $conversation, string $content, string $senderType = 'user', ?int $senderId = null): array
    {
        $message = LiveChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'content' => $content,
            'sent_at' => now(),
        ]);

        $result = ['message' => $message, 'reply' => null, 'handoff' => false];

        if ($senderType === 'user') {
            $handoff = $this->getActiveHandoff($conversation);
            if ($handoff) {
                $this->handoffService->sendCustomerMessage(
                    $handoff,
                    $content,
                    $senderId ?? $conversation->user_id
                );

                return [
                    'message' => $message,
                    'reply' => null,
                    'handoff' => $this->formatHandoffPayload($handoff),
                ];
            }

            // AI 自动回复
            if (config('live-chat.ai.enabled', true)) {
                $conversation->touch();
                $reply = $this->getAiResponse($conversation, $content);
                $result['reply'] = $reply;

                // 判断是否需要转人工
                if ($this->shouldHandoff($conversation)) {
                    $created = $this->createHandoff($conversation, 'AI无法解决');
                    $result['handoff'] = $this->formatHandoffPayload($created);
                }
            }
        }

        return $result;
    }

    protected function getActiveHandoff(LiveChatConversation $conversation): ?HandoffRequest
    {
        return HandoffRequest::where('live_chat_conversation_id', $conversation->id)
            ->whereIn('status', ['queued', 'assigned', 'in_progress'])
            ->latest('id')
            ->first();
    }

    protected function formatHandoffPayload(HandoffRequest $handoff): array
    {
        return [
            'id' => $handoff->id,
            'status' => $handoff->status,
            'queue_position' => $handoff->queue_position,
        ];
    }

    /**
     * AI 自动回复
     */
    protected function getAiResponse(LiveChatConversation $conversation, string $message): ?LiveChatMessage
    {
        try {
            $contextMessages = LiveChatMessage::where('conversation_id', $conversation->id)
                ->orderByDesc('sent_at')
                ->take(config('live-chat.ai.context_window', 10))
                ->get()
                ->reverse();

            $replyContent = $this->callAiApi($contextMessages, $message);

            if (!$replyContent) {
                return null;
            }

            return LiveChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'ai',
                'content' => $replyContent,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AI回复失败', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 调用AI API（M2-41b 对话引擎集成点）
     */
    protected function callAiApi($contextMessages, string $message): ?string
    {
        // 简化实现：根据关键词匹配回复
        $keywords = [
            '激活' => '激活License请在客户端使用激活功能，输入License Key即可完成激活。如有问题请查看帮助中心。',
            '续费' => '续费可在客户门户的"账单与发票"页面操作。续费成功后License会自动延期。',
            '退款' => '退款政策请查看服务条款。如需申请退款，请在订单页面发起退款申请。',
            '设备' => '设备管理请在客户门户的"我的设备"页面查看。每个License有设备数量限制。',
            '升级' => '升级套餐请联系销售或通过商品商店选择更高版本方案。',
            'hello' => '您好！请问有什么可以帮助您的？',
            'hi' => '您好！请问有什么可以帮助您的？',
            'help' => '我可以帮您解答关于License激活、续费、设备管理、退款等问题。',
        ];

        $lower = strtolower($message);
        foreach ($keywords as $key => $reply) {
            if (str_contains($lower, $key)) {
                return $reply;
            }
        }

        // 默认回复
        $greeting = config('live-chat.messages.greeting', '您好！');
        if (LiveChatMessage::where('conversation_id', $contextMessages->first()?->conversation_id)->count() <= 2) {
            return $greeting;
        }

        return '收到您的消息，已为您转接人工客服。如需即时帮助，请描述您的问题，或查看帮助中心。';
    }

    /**
     * 判断是否需要转人工
     */
    protected function shouldHandoff(LiveChatConversation $conversation): bool
    {
        $messageCount = LiveChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', 'user')->count();

        $maxMessages = config('live-chat.handoff.auto_handoff_after_messages', 5);
        if ($messageCount >= $maxMessages) {
            return true;
        }

        $createdSince = $conversation->created_at;
        $maxSeconds = config('live-chat.handoff.auto_handoff_after_seconds', 120);
        if ($createdSince && now()->diffInSeconds($createdSince) >= $maxSeconds) {
            return true;
        }

        return false;
    }

    /**
     * 创建转人工（统一使用 HandoffService）
     */
    public function createHandoff(LiveChatConversation $conversation, ?string $reason = null): HandoffRequest
    {
        $conversation->update(['status' => 'handoff']);

        LiveChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'ai',
            'content' => config('live-chat.messages.handoff', '正在为您转接人工客服...'),
            'sent_at' => now(),
        ]);

        return $this->handoffService->createLiveChatHandoff($conversation, $reason);
    }

    /**
     * 客服接单（统一使用 HandoffService）
     */
    public function acceptHandoff(int $handoffId, int $agentId): HandoffRequest
    {
        $handoff = HandoffRequest::findOrFail($handoffId);
        $agent = \App\Models\User::findOrFail($agentId);

        return $this->handoffService->acceptLiveChatHandoff($handoff, $agent);
    }

    /**
     * 关闭会话
     */
    public function closeConversation(int $conversationId, ?int $rating = null, ?string $comment = null): LiveChatConversation
    {
        $conversation = LiveChatConversation::findOrFail($conversationId);
        $conversation->update([
            'status' => 'closed',
            'rating' => $rating,
            'rating_comment' => $comment,
            'closed_at' => now(),
        ]);

        // 关闭关联的待处理转接
        HandoffRequest::where('live_chat_conversation_id', $conversationId)
            ->whereIn('status', ['queued', 'assigned', 'in_progress'])
            ->update(['status' => 'closed', 'closed_at' => now()]);

        return $conversation->fresh();
    }

    /**
     * 获取会话消息
     */
    public function getMessages(int $conversationId, int $limit = 50): array
    {
        return LiveChatMessage::where('conversation_id', $conversationId)
            ->orderBy('sent_at')
            ->take($limit)
            ->get()
            ->toArray();
    }

    /**
     * 管理端：获取会话列表
     */
    public function getConversations(int $tenantId, array $filters = []): array
    {
        $query = LiveChatConversation::where('tenant_id', $tenantId)
            ->with(['messages' => fn($q) => $q->latest()->take(1)])
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage)->withQueryString()->toArray();
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        return [
            'active' => LiveChatConversation::where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'waiting' => LiveChatConversation::where('tenant_id', $tenantId)->where('status', 'handoff')->count(),
            'closed_today' => LiveChatConversation::where('tenant_id', $tenantId)
                ->where('status', 'closed')->whereDate('closed_at', today())->count(),
            'avg_rating' => LiveChatConversation::where('tenant_id', $tenantId)
                ->whereNotNull('rating')->avg('rating'),
            'pending_handoffs' => HandoffRequest::where('tenant_id', $tenantId)
                ->whereNotNull('live_chat_conversation_id')
                ->where('status', 'queued')
                ->count(),
        ];
    }
}
