<?php

namespace App\Http\Controllers\Api;

use App\Events\HandoffMessageSent;
use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HandoffRequest;
use App\Models\RagConversation;
use App\Models\User;
use App\Models\UserConversation;
use App\Services\HandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * AI → 人工客服转接管理
 *
 * 分管：客户端的转接请求和管理端的客服队列
 */
use App\Services\IpGeoService;

class HandoffController extends Controller
{
    public function __construct(
        protected HandoffService $handoffService,
    ) {}

    /**
     * 提交转接请求（客户端调用）
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|integer',
            'reason' => 'sometimes|string|max:50',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'message' => 'sometimes|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $conversationId = (int) $request->input('conversation_id');
        $reason = $request->input('reason', 'user_request');
        $options = [
            'priority' => $request->input('priority'),
            'user_id' => $request->user()?->id,
            'customer_id' => $request->user()?->customer_id,
            'tenant_id' => $request->user()?->tenant_id,
            'intent' => $request->input('intent'),
            'confidence' => $request->input('confidence'),
            'source' => 'portal',
            'page_url' => $request->header('Referer'),
            'user_agent' => $request->userAgent(),
        ];

        try {
            if (RagConversation::whereKey($conversationId)->exists()) {
                $conversation = RagConversation::findOrFail($conversationId);
                $handoff = $this->handoffService->createHandoff($conversation, $reason, $options);
            } elseif (UserConversation::whereKey($conversationId)->exists()) {
                $conversation = UserConversation::findOrFail($conversationId);
                $handoff = $this->handoffService->createUserChatHandoff($conversation, $reason, $options);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '无效的会话 ID',
                    'errors' => ['conversation_id' => ['会话不存在']],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => '已提交转接请求',
                'data' => [
                    'handoff_id' => $handoff->id,
                    'status' => $handoff->status,
                    'queue_position' => $handoff->queue_position,
                    'assigned_agent' => $handoff->assignee?->only(['id', 'name']),
                    'user_conversation_id' => $handoff->user_conversation_id,
                    'dm_conversation_id' => $handoff->dmConversationId(),
                    'context' => $handoff->conversation_context,
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '转接失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取转接状态（客户端轮询）
     */
    public function status(HandoffRequest $handoff): JsonResponse
    {
        $handoff->load([
            'assignee:id,name',
            'messages' => fn($q) => $q->latest()->limit(10),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $handoff->id,
                'status' => $handoff->status,
                'reason' => $handoff->reason,
                'reason_label' => $handoff->reasonLabel(),
                'priority' => $handoff->priority,
                'queue_position' => $handoff->queue_position,
                'wait_time_formatted' => $handoff->waitTimeFormatted(),
                'assignee' => $handoff->assignee?->only(['id', 'name']),
                'user_conversation_id' => $handoff->user_conversation_id,
                'dm_conversation_id' => $handoff->dmConversationId(),
                'messages' => $handoff->messages,
                'assigned_at' => $handoff->assigned_at?->toIso8601String(),
                'accepted_at' => $handoff->accepted_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * 发送消息（客户或已接单的客服）
     */
    public function sendMessage(Request $request, HandoffRequest $handoff): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:1|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $content = $request->input('content');

        try {
            if ($handoff->assigned_to && (int) $handoff->assigned_to === (int) $user->id) {
                $this->authorize('update', $handoff);
                $message = $this->handoffService->sendMessage($handoff, $user, $content);
                event(new HandoffMessageSent($handoff, $content, 'agent'));
            } else {
                $message = $this->handoffService->sendCustomerMessage(
                    $handoff,
                    $content,
                    $user?->id
                );
                event(new HandoffMessageSent($handoff, $content, 'customer'));
            }

            return response()->json([
                'success' => true,
                'data' => $message,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 获取转接对话消息
     */
    public function getMessages(HandoffRequest $handoff): JsonResponse
    {
        $handoff->load(['messages.user:id,name', 'messages' => fn($q) => $q->latest()->limit(50)]);

        return response()->json([
            'success' => true,
            'data' => [
                'handoff_id' => $handoff->id,
                'status' => $handoff->status,
                'messages' => $handoff->messages->reverse()->values(),
            ],
        ]);
    }

    /**
     * 客户的转接历史
     */
    public function myHistory(Request $request): JsonResponse
    {
        $customerId = $request->user()?->customer_id;

        if (!$customerId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $handoffs = $this->handoffService->getCustomerHandoffs($customerId);

        return response()->json(['success' => true, 'data' => $handoffs]);
    }

    // ========================
    // Admin 端 — 客服队列
    // ========================

    /**
     * 转接队列（排队中的请求）
     */
    public function queue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HandoffRequest::class);

        $queue = $this->handoffService->getQueue($request->user()->tenant_id);

        return response()->json(['success' => true, 'data' => $queue]);
    }

    /**
     * 客服的活跃对话列表
     */
    public function myConversations(Request $request): JsonResponse
    {
        $conversations = $this->handoffService->getAgentConversations($request->user()->id);

        return response()->json(['success' => true, 'data' => $conversations]);
    }

    /**
     * 客服接受转接
     */
    public function accept(Request $request, HandoffRequest $handoff): JsonResponse
    {
        $this->authorize('update', $handoff);

        try {
            $handoff = $this->handoffService->accept($handoff, $request->user());
            $handoff->load([
                'customer.user:id,name,email',
                'messages',
                'conversation',
                'userChatConversation',
                'user:id,name',
            ]);
            $data = $handoff->toArray();
            $data['dm_conversation_id'] = $handoff->dmConversationId();

            return response()->json([
                'success' => true,
                'message' => '已接受转接',
                'data' => $data,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 客服发送消息
     */
    public function agentSend(Request $request, HandoffRequest $handoff): JsonResponse
    {
        $this->authorize('update', $handoff);

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:1|max:20000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $message = $this->handoffService->sendMessage(
                $handoff,
                $request->user(),
                $request->input('content')
            );

            // 推送实时通知给客户
            event(new HandoffMessageSent($handoff, $request->input('content'), 'agent'));

            return response()->json([
                'success' => true,
                'data' => $message,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 客服关闭转接
     */
    public function close(Request $request, HandoffRequest $handoff): JsonResponse
    {
        $this->authorize('update', $handoff);

        $this->handoffService->close(
            $handoff,
            $request->user(),
            $request->input('note')
        );

        return response()->json([
            'success' => true,
            'message' => '对话已关闭',
        ]);
    }

    /**
     * 满意度评价
     */
    public function rate(Request $request, HandoffRequest $handoff): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->handoffService->rateHandoff(
                $handoff,
                $validated['rating'],
                $validated['comment'] ?? null
            );

            return ApiResponse::success(
                $result->only(['id', 'rating', 'rating_comment', 'rated_at']),
                '感谢您的评价！'
            );
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 转交给其他客服
     */
    public function transfer(Request $request, HandoffRequest $handoff): JsonResponse
    {
        $this->authorize('update', $handoff);

        $validator = Validator::make($request->all(), [
            'to_agent_id' => 'required|exists:users,id',
            'note' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $targetAgent = User::findOrFail($request->input('to_agent_id'));

        $this->handoffService->transfer(
            $handoff,
            $request->user(),
            $targetAgent,
            $request->input('note')
        );

        return response()->json([
            'success' => true,
            'message' => '已转交',
        ]);
    }

    /**
     * 更新客服状态
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:online,away,busy,offline',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->handoffService->updateAgentStatus(
            $request->user(),
            $request->input('status')
        );

        return response()->json([
            'success' => true,
            'message' => '状态已更新',
        ]);
    }

    /**
     * 在线客服列表
     */
    public function onlineAgents(Request $request): JsonResponse
    {
        $agents = User::whereIn('agent_status', ['online', 'away', 'busy'])
            ->whereHas('roles', fn($q) => $q->where('name', '客服'))
            ->get(['id', 'name', 'agent_status', 'max_concurrent_chats']);

        return response()->json(['success' => true, 'data' => $agents]);
    }

    /**
     * 转接统计
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HandoffRequest::class);

        return response()->json([
            'success' => true,
            'data' => $this->handoffService->getStats($request->user()->tenant_id),
        ]);
    }

    /**
     * 队列统计
     */
    public function queueStats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HandoffRequest::class);

        return response()->json([
            'success' => true,
            'data' => $this->handoffService->getQueueStats($request->user()->tenant_id),
        ]);
    }

    /**
     * 获取转接详情（Admin）
     */
    public function show(HandoffRequest $handoff): JsonResponse
    {
        $this->authorize('view', $handoff);

        $handoff->load([
            'customer.user:id,name,email',
            'user:id,name',
            'assignee:id,name',
            'messages.user:id,name',
            'actions.user:id,name',
            'conversation',
            'userChatConversation',
            'ticket',
        ]);

        return response()->json(['success' => true, 'data' => array_merge($handoff->toArray(), [
            'dm_conversation_id' => $handoff->dmConversationId(),
        ])]);
    }

    /**
     * 获取访客信息（IP 地理定位 + 来源 + 设备）
     */
    public function visitorInfo(HandoffRequest $handoff): JsonResponse
    {
        $this->authorize('view', $handoff);

        $metadata = $handoff->metadata ?? [];
        $userAgent = $metadata['user_agent'] ?? '';
        $ip = $metadata['ip_address'] ?? ($handoff->user?->last_login_ip ?? request()->ip());

        $geoService = app(IpGeoService::class);
        $geo = $geoService->locate($ip);
        $ua = $geoService->parseUserAgent($userAgent);

        return response()->json(['success' => true, 'data' => [
            'ip' => $ip,
            'country' => $geo['country'],
            'region' => $geo['region'],
            'city' => $geo['city'],
            'isp' => $geo['isp'],
            'browser' => $ua['browser'],
            'os' => $ua['os'],
            'device' => $ua['device'],
            'page_url' => $metadata['page_url'] ?? null,
            'source' => $metadata['source'] ?? 'chat',
            'user_agent' => $userAgent,
        ]]);
    }
}
