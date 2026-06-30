<?php

namespace App\Services;

use App\Models\AgentMessage;
use App\Models\HandoffAction;
use App\Models\HandoffRequest;
use App\Models\LiveChatConversation;
use App\Models\RagConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI → 人工客服转接服务
 *
 * 管理转接请求的完整生命周期：
 * 创建 → 排队 → 分配 → 人工处理 → 关闭
 * 支持：上下文打包、客服状态管理、队列优先级、操作审计
 */
class HandoffService
{
    /**
     * 创建转接请求
     */
    public function createHandoff(
        RagConversation $conversation,
        string $reason,
        array $options = []
    ): HandoffRequest {
        return DB::transaction(function () use ($conversation, $reason, $options) {
            // 检查是否已有活跃的转接请求
            $existing = HandoffRequest::where('conversation_id', $conversation->id)
                ->whereIn('status', ['queued', 'assigned', 'in_progress'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $priority = $options['priority'] ?? $this->determinePriority($reason, $options);
            $queuePosition = HandoffRequest::where('tenant_id', $conversation->tenant_id)
                ->where('status', 'queued')
                ->count() + 1;

            $handoff = HandoffRequest::create([
                'tenant_id' => $conversation->tenant_id ?? $options['tenant_id'],
                'conversation_id' => $conversation->id,
                'customer_id' => $options['customer_id'] ?? $conversation->customer_id ?? null,
                'user_id' => $options['user_id'] ?? $conversation->user_id ?? null,
                'ticket_id' => $options['ticket_id'] ?? null,
                'reason' => $reason,
                'status' => 'queued',
                'priority' => $priority,
                'queue_position' => $queuePosition,
                'conversation_context' => $this->buildContext($conversation, $options),
                'metadata' => [
                    'intent' => $options['intent'] ?? null,
                    'confidence' => $options['confidence'] ?? null,
                    'source' => $options['source'] ?? 'chat',
                    'page_url' => $options['page_url'] ?? null,
                    'user_agent' => $options['user_agent'] ?? null,
                    'ip_address' => $options['ip_address'] ?? request()->ip(),
                    'country' => $options['country'] ?? null,
                    'city' => $options['city'] ?? null,
                ],
                'queued_at' => now(),
            ]);

            // 尝试自动分配（不阻塞，如果没有可用客服就留在队列）
            $this->tryAutoAssign($handoff);

            Log::info('Handoff: created', [
                'handoff_id' => $handoff->id,
                'conversation_id' => $conversation->id,
                'reason' => $reason,
                'priority' => $priority,
                'queue_position' => $queuePosition,
            ]);

            return $handoff;
        });
    }

    /**
     * 客服接受转接
     */
    public function accept(HandoffRequest $handoff, User $agent): HandoffRequest
    {
        if ($handoff->assigned_to && $handoff->assigned_to !== $agent->id) {
            throw new \RuntimeException('该转接已分配给其他客服');
        }

        DB::transaction(function () use ($handoff, $agent) {
            $handoff->assignTo($agent);
            $handoff->accept();

            // 记录操作日志
            $handoff->actions()->create([
                'user_id' => $agent->id,
                'action' => 'accept',
                'note' => '客服接受转接',
            ]);

            // 发送系统消息
            $handoff->messages()->create([
                'user_id' => $agent->id,
                'content' => "您好！我是客服 {$agent->name}，已接过您的对话，请问有什么可以帮您的？",
                'sender_type' => 'system',
            ]);
        });

        Log::info('Handoff: accepted', [
            'handoff_id' => $handoff->id,
            'agent_id' => $agent->id,
        ]);

        return $handoff->fresh();
    }

    /**
     * 发送客服消息
     */
    public function sendMessage(HandoffRequest $handoff, User $agent, string $content): AgentMessage
    {
        if ($handoff->status !== 'in_progress') {
            throw new \RuntimeException('对话未进行中，无法发送消息');
        }

        if ($handoff->assigned_to !== $agent->id) {
            throw new \RuntimeException('您不是当前对话的负责人');
        }

        $message = $handoff->messages()->create([
            'user_id' => $agent->id,
            'content' => $content,
            'sender_type' => 'agent',
            'is_read' => false,
        ]);

        // 更新 handoff 状态
        $handoff->touch();

        return $message;
    }

    /**
     * 客户发送消息（转接后的对话）
     */
    public function sendCustomerMessage(HandoffRequest $handoff, string $content, ?int $userId = null): AgentMessage
    {
        if (!in_array($handoff->status, ['assigned', 'in_progress'])) {
            throw new \RuntimeException('当前没有活跃的客服对话');
        }

        $message = $handoff->messages()->create([
            'user_id' => $userId,
            'content' => $content,
            'sender_type' => 'customer',
            'is_read' => false,
        ]);

        $handoff->touch();

        return $message;
    }

    /**
     * 关闭转接（客服操作）
     */
    public function close(HandoffRequest $handoff, User $agent, ?string $note = null): void
    {
        DB::transaction(function () use ($handoff, $agent, $note) {
            $handoff->resolve();
            $handoff->close();

            $handoff->actions()->create([
                'user_id' => $agent->id,
                'action' => 'close',
                'note' => $note ?? '客服关闭对话',
            ]);

            // 系统消息
            $handoff->messages()->create([
                'user_id' => null,
                'content' => '本次客服对话已结束。如果还有问题，欢迎随时联系我们。',
                'sender_type' => 'system',
            ]);
        });

        Log::info('Handoff: closed', [
            'handoff_id' => $handoff->id,
            'agent_id' => $agent->id,
        ]);
    }

    /**
     * 转交给其他客服
     */
    public function transfer(HandoffRequest $handoff, User $fromAgent, User $toAgent, ?string $note = null): void
    {
        DB::transaction(function () use ($handoff, $fromAgent, $toAgent, $note) {
            $oldAgent = $handoff->assignee;

            $handoff->update([
                'assigned_to' => $toAgent->id,
                'status' => 'assigned',
                'assigned_at' => now(),
                'accepted_at' => null,
            ]);

            $handoff->actions()->create([
                'user_id' => $fromAgent->id,
                'action' => 'transfer',
                'note' => $note ?? "转交给 {$toAgent->name}",
                'metadata' => [
                    'from_agent_id' => $oldAgent?->id,
                    'from_agent_name' => $oldAgent?->name,
                    'to_agent_id' => $toAgent->id,
                    'to_agent_name' => $toAgent->name,
                ],
            ]);

            $handoff->messages()->create([
                'user_id' => null,
                'content' => "对话已转交给客服 {$toAgent->name}，请稍候...",
                'sender_type' => 'system',
            ]);
        });

        Log::info('Handoff: transferred', [
            'handoff_id' => $handoff->id,
            'from' => $fromAgent->id,
            'to' => $toAgent->id,
        ]);
    }

    /**
     * 获取客服队列（排队中）
     */
    public function getQueue(int $tenantId): array
    {
        $queued = HandoffRequest::where('tenant_id', $tenantId)
            ->where('status', 'queued')
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('created_at')
            ->with(['customer.user:id,name,email', 'conversation'])
            ->get();

        return $queued->toArray();
    }

    /**
     * 获取客服的活跃对话
     */
    public function getAgentConversations(int $agentId): array
    {
        $conversations = HandoffRequest::where('assigned_to', $agentId)
            ->whereIn('status', ['assigned', 'in_progress'])
            ->with([
                'customer.user:id,name,email',
                'messages' => fn($q) => $q->latest()->limit(5),
            ])
            ->orderBy('priority')
            ->orderBy('created_at')
            ->get();

        return $conversations->toArray();
    }

    /**
     * 获取客户自己的转接记录
     */
    public function getCustomerHandoffs(int $customerId): array
    {
        return HandoffRequest::where('customer_id', $customerId)
            ->with(['assignee:id,name', 'messages' => fn($q) => $q->latest()->limit(3)])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 更新客服状态
     */
    public function updateAgentStatus(User $agent, string $status): void
    {
        $allowed = ['online', 'away', 'busy', 'offline'];
        if (!in_array($status, $allowed)) {
            throw new \InvalidArgumentException("无效状态: {$status}");
        }

        $agent->update([
            'agent_status' => $status,
            'agent_status_changed_at' => now(),
        ]);

        // 如果离线，释放未接受的分配
        if ($status === 'offline') {
            HandoffRequest::where('assigned_to', $agent->id)
                ->where('status', 'assigned')
                ->update([
                    'assigned_to' => null,
                    'status' => 'queued',
                    'assigned_at' => null,
                ]);
        }

        Log::info('Handoff: agent status changed', [
            'agent_id' => $agent->id,
            'status' => $status,
        ]);
    }

    /**
     * 获取队列统计
     */
    public function getQueueStats(int $tenantId): array
    {
        $now = now();
        $totalQueued = HandoffRequest::where('tenant_id', $tenantId)->where('status', 'queued')->count();
        $urgentQueued = HandoffRequest::where('tenant_id', $tenantId)
            ->where('status', 'queued')->where('priority', 'urgent')->count();

        $avgWaitSeconds = HandoffRequest::where('tenant_id', $tenantId)
            ->where('status', 'queued')
            ->whereNotNull('queued_at')
            ->get()
            ->avg(fn($h) => $now->diffInSeconds($h->queued_at));

        // 在线客服数
        $onlineAgents = User::where('agent_status', 'online')->count();
        $busyAgents = User::where('agent_status', 'busy')->count();

        // 今日处理数
        $todayResolved = HandoffRequest::where('tenant_id', $tenantId)
            ->whereIn('status', ['resolved', 'closed'])
            ->whereDate('updated_at', $now->toDateString())
            ->count();

        return [
            'total_queued' => $totalQueued,
            'urgent_queued' => $urgentQueued,
            'avg_wait_seconds' => (int) round($avgWaitSeconds ?: 0),
            'avg_wait_formatted' => $avgWaitSeconds ? $this->formatDuration((int) round($avgWaitSeconds)) : '—',
            'online_agents' => $onlineAgents,
            'busy_agents' => $busyAgents,
            'today_resolved' => $todayResolved,
        ];
    }

    /**
     * 提交满意度评价
     */
    public function rateHandoff(HandoffRequest $handoff, int $rating, ?string $comment = null): HandoffRequest
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('评分必须在 1-5 之间');
        }

        if (!in_array($handoff->status, ['resolved', 'closed'])) {
            throw new \RuntimeException('只能对已结束的对话进行评价');
        }

        if ($handoff->rating !== null) {
            throw new \RuntimeException('该对话已评价过');
        }

        $handoff->update([
            'rating' => $rating,
            'rating_comment' => $comment,
            'rated_at' => now(),
        ]);

        // 更新客服绩效
        if ($handoff->assigned_to) {
            \App\Models\AgentPerformanceLog::updateOrCreate(
                [
                    'agent_id' => $handoff->assigned_to,
                    'date' => today()->toDateString(),
                ],
                [
                    'satisfaction_score' => \DB::raw("
                        COALESCE(
                            (SELECT AVG(rating) FROM handoff_requests
                             WHERE assigned_to = {$handoff->assigned_to}
                             AND rating IS NOT NULL),
                            0
                        )
                    "),
                ]
            );
        }

        Log::info('Handoff: rated', [
            'handoff_id' => $handoff->id,
            'rating' => $rating,
            'agent_id' => $handoff->assigned_to,
        ]);

        return $handoff->fresh();
    }

    /**
     * 获取转接统计
     */
    public function getStats(int $tenantId): array
    {
        $total = HandoffRequest::where('tenant_id', $tenantId)->count();
        $queued = HandoffRequest::where('tenant_id', $tenantId)->where('status', 'queued')->count();
        $active = HandoffRequest::where('tenant_id', $tenantId)->whereIn('status', ['assigned', 'in_progress'])->count();
        $resolvedToday = HandoffRequest::where('tenant_id', $tenantId)
            ->whereIn('status', ['resolved', 'closed'])
            ->whereDate('updated_at', today())
            ->count();

        $byReason = HandoffRequest::where('tenant_id', $tenantId)
            ->selectRaw('reason, COUNT(*) as count')
            ->groupBy('reason')
            ->pluck('count', 'reason')
            ->toArray();

        $avgSatisfaction = (float) HandoffRequest::where('tenant_id', $tenantId)
            ->whereNotNull('rating')
            ->avg('rating') ?: 0;

        $ratedCount = HandoffRequest::where('tenant_id', $tenantId)
            ->whereNotNull('rating')->count();

        $ratingDistribution = HandoffRequest::where('tenant_id', $tenantId)
            ->whereNotNull('rating')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return [
            'total' => $total,
            'queued' => $queued,
            'active' => $active,
            'resolved_today' => $resolvedToday,
            'by_reason' => $byReason,
            'avg_satisfaction' => round($avgSatisfaction, 2),
            'rated_count' => $ratedCount,
            'rating_distribution' => $ratingDistribution,
        ];
    }

    /**
     * 构建上下文快照
     */
    protected function buildContext(RagConversation $conversation, array $options): array
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        return [
            'messages' => $messages->toArray(),
            'total_messages' => $conversation->messages()->count(),
            'session_id' => $conversation->session_id,
            'created_at' => $conversation->created_at->toIso8601String(),
            'last_activity' => $conversation->updated_at->toIso8601String(),
            'intent_history' => $options['intent_history'] ?? [],
            'customer_info' => $options['customer_info'] ?? null,
        ];
    }

    /**
     * 自动分配
     */
    protected function tryAutoAssign(HandoffRequest $handoff): void
    {
        $availableAgent = User::where('agent_status', 'online')
            ->whereHas('roles', fn($q) => $q->where('name', '客服'))
            ->withCount(['activeHandoffs' => fn($q) => $q->whereIn('status', ['assigned', 'in_progress'])])
            ->having('active_handoffs_count', '<', DB::raw('users.max_concurrent_chats'))
            ->orderBy('active_handoffs_count')
            ->first();

        if ($availableAgent) {
            $handoff->assignTo($availableAgent);
            Log::info('Handoff: auto-assigned', [
                'handoff_id' => $handoff->id,
                'agent_id' => $availableAgent->id,
            ]);
        }
    }

    /**
     * 根据原因确定优先级
     */
    protected function determinePriority(string $reason, array $options): string
    {
        if (!empty($options['priority'])) {
            return $options['priority'];
        }

        return match ($reason) {
            'sensitive_topic' => 'high',
            'error_limit' => 'high',
            'user_request' => 'medium',
            'low_confidence' => 'medium',
            'complex_query' => 'low',
            default => 'medium',
        };
    }

    /**
     * 格式化时长
     */
    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) return "{$seconds}秒";
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;
        if ($minutes < 60) return "{$minutes}分{$secs}秒";
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return "{$hours}小时{$mins}分";
    }

    // ═══════════════════════════════════════
    // 在线客服 (LiveChat) 转接支持
    // ═══════════════════════════════════════

    /**
     * 从在线客服对话创建转接请求
     */
    public function createLiveChatHandoff(
        LiveChatConversation $conversation,
        ?string $reason = null,
        array $options = []
    ): HandoffRequest {
        return DB::transaction(function () use ($conversation, $reason, $options) {
            // 检查是否已有活跃的转接请求
            $existing = HandoffRequest::where('live_chat_conversation_id', $conversation->id)
                ->whereIn('status', ['queued', 'assigned', 'in_progress'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $handoff = HandoffRequest::create([
                'tenant_id' => $conversation->tenant_id,
                'conversation_id' => null,
                'live_chat_conversation_id' => $conversation->id,
                'customer_id' => null,
                'user_id' => $conversation->user_id,
                'ticket_id' => null,
                'reason' => $reason ?? 'low_confidence',
                'status' => 'queued',
                'priority' => 'medium',
                'queue_position' => HandoffRequest::where('tenant_id', $conversation->tenant_id)
                    ->where('status', 'queued')->count() + 1,
                'conversation_context' => [
                    'source' => 'live_chat',
                    'department' => $conversation->department,
                    'session_id' => $conversation->session_id,
                    'created_at' => $conversation->created_at->toIso8601String(),
                    'last_activity' => $conversation->updated_at->toIso8601String(),
                ],
                'metadata' => [
                    'source' => 'live_chat',
                    'department' => $conversation->department,
                    'page_url' => $options['page_url'] ?? null,
                ],
                'queued_at' => now(),
            ]);

            // 尝试自动分配
            $this->tryAutoAssign($handoff);

            Log::info('Handoff: live-chat created', [
                'handoff_id' => $handoff->id,
                'live_chat_conversation_id' => $conversation->id,
                'reason' => $reason,
            ]);

            return $handoff;
        });
    }

    /**
     * 在线客服接受转接
     */
    public function acceptLiveChatHandoff(HandoffRequest $handoff, User $agent): HandoffRequest
    {
        if ($handoff->assigned_to && $handoff->assigned_to !== $agent->id) {
            throw new \RuntimeException('该转接已分配给其他客服');
        }

        DB::transaction(function () use ($handoff, $agent) {
            $handoff->assignTo($agent);
            $handoff->accept();

            $handoff->actions()->create([
                'user_id' => $agent->id,
                'action' => 'accept',
                'note' => '客服接受转接',
            ]);

            // 更新 LiveChat 会话状态
            if ($handoff->liveChatConversation) {
                $handoff->liveChatConversation->update([
                    'assigned_to' => $agent->id,
                    'status' => 'active',
                ]);
            }

            $handoff->messages()->create([
                'user_id' => $agent->id,
                'content' => "您好！我是客服 {$agent->name}，已接过您的对话，请问有什么可以帮您的？",
                'sender_type' => 'system',
            ]);
        });

        return $handoff->fresh();
    }

    /**
     * 在线客服关闭转接
     */
    public function closeLiveChatHandoff(HandoffRequest $handoff, User $agent, ?string $note = null): void
    {
        DB::transaction(function () use ($handoff, $agent, $note) {
            $handoff->resolve();
            $handoff->close();

            $handoff->actions()->create([
                'user_id' => $agent->id,
                'action' => 'close',
                'note' => $note ?? '客服关闭对话',
            ]);

            // 关闭 LiveChat 会话
            if ($handoff->liveChatConversation) {
                $handoff->liveChatConversation->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);
            }

            $handoff->messages()->create([
                'user_id' => null,
                'content' => '本次客服对话已结束。如果还有问题，欢迎随时联系我们。',
                'sender_type' => 'system',
            ]);
        });
    }

    /**
     * 获取在线客服待处理转接
     */
    public function getLiveChatPendingHandoffs(int $tenantId): array
    {
        return HandoffRequest::where('status', 'queued')
            ->whereNotNull('live_chat_conversation_id')
            ->where('tenant_id', $tenantId)
            ->with(['liveChatConversation.messages', 'assignee:id,name'])
            ->orderByDesc('id')
            ->get()
            ->toArray();
    }
}
