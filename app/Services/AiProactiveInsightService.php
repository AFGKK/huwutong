<?php

namespace App\Services;

use App\Models\AiInsight;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiProactiveInsightService
{
    protected LlmService $llm;
    protected NotificationService $notifications;
    protected PromptTemplateService $promptTemplates;

    public function __construct(
        LlmService $llm,
        NotificationService $notifications,
        PromptTemplateService $promptTemplates,
    ) {
        $this->llm = $llm;
        $this->notifications = $notifications;
        $this->promptTemplates = $promptTemplates;
    }

    /**
     * 扫描所有需要主动洞察的对话
     * 返回本次生成的洞察数量
     */
    public function scanAll(): int
    {
        if (!config('ai-proactive.enabled', true)) {
            return 0;
        }

        $totalGenerated = 0;
        $maxScan = config('ai-proactive.max_insights_per_scan', 20);
        $timeout = config('ai-proactive.unreplied_timeout', 30);

        // 1. 扫描 AI 好友对话：AI 回复后用户长时间未回复
        $aiConversations = $this->findUnrepliedAiConversations($timeout);

        foreach ($aiConversations as $conv) {
            if ($totalGenerated >= $maxScan) break;

            $canPush = $this->canPushToUser($conv->user_id);
            if (!$canPush) continue;

            $insight = $this->generateFollowUpInsight($conv);
            if ($insight) {
                $this->deliverInsight($insight);
                $totalGenerated++;
            }
        }

        // 2. 扫描客服对话：用户发消息后客服长时间未回复
        if ($totalGenerated < $maxScan) {
            $pendingAgentConvs = $this->findPendingAgentConversations($timeout);
            foreach ($pendingAgentConvs as $conv) {
                if ($totalGenerated >= $maxScan) break;

                $canPush = $this->canPushToUser($conv->user_id);
                if (!$canPush) continue;

                $insight = $this->generateAgentFollowUpInsight($conv);
                if ($insight) {
                    $this->deliverInsight($insight);
                    $totalGenerated++;
                }
            }
        }

        Log::info('AI 主动洞察扫描完成', [
            'generated' => $totalGenerated,
            'max_allowed' => $maxScan,
        ]);

        return $totalGenerated;
    }

    /**
     * 查找 AI 回复后用户未再发言的对话
     */
    protected function findUnrepliedAiConversations(int $timeoutMinutes): \Illuminate\Support\Collection
    {
        $deadline = now()->subMinutes($timeoutMinutes);

        // 子查询：找到每个对话中最后一条消息
        $latestMsgSub = ConversationMessage::select('conversation_id', DB::raw('MAX(created_at) as last_msg_at'))
            ->groupBy('conversation_id');

        return ConversationMessage::from('conversation_messages as cm')
            ->joinSub($latestMsgSub, 'lm', function ($join) {
                $join->on('cm.conversation_id', '=', 'lm.conversation_id')
                    ->whereRaw('cm.created_at = lm.last_msg_at');
            })
            ->join('conversation_participants as cp', 'cm.conversation_id', '=', 'cp.conversation_id')
            ->where('cm.message_type', 'ai_reply')
            ->where('cm.created_at', '<', $deadline)
            ->where('cm.created_at', '>=', now()->subHours(24)) // 只查最近24小时
            ->whereNull('cm.deleted_at')
            ->select([
                'cm.conversation_id',
                'cm.content as last_ai_message',
                'cm.created_at as replied_at',
                'cp.user_id',
            ])
            ->distinct()
            ->limit(50)
            ->get();
    }

    /**
     * 查找用户发消息后客服未回复的对话
     */
    protected function findPendingAgentConversations(int $timeoutMinutes): \Illuminate\Support\Collection
    {
        $deadline = now()->subMinutes($timeoutMinutes);

        $latestMsgSub = ConversationMessage::select('conversation_id', DB::raw('MAX(created_at) as last_msg_at'))
            ->where('message_type', 'text')
            ->groupBy('conversation_id');

        return ConversationMessage::from('conversation_messages as cm')
            ->joinSub($latestMsgSub, 'lm', function ($join) {
                $join->on('cm.conversation_id', '=', 'lm.conversation_id')
                    ->whereRaw('cm.created_at = lm.last_msg_at');
            })
            ->join('conversation_participants as cp', 'cm.conversation_id', '=', 'cp.conversation_id')
            ->whereIn('cm.message_type', ['text', 'image', 'file'])
            ->where('cm.created_at', '<', $deadline)
            ->where('cm.created_at', '>=', now()->subHours(24))
            ->whereNull('cm.deleted_at')
            ->where('cm.sender_id', '!=', 0) // 不是系统消息
            ->select([
                'cm.conversation_id',
                'cm.content as last_user_message',
                'cm.created_at as last_msg_at',
                'cm.sender_id',
                'cp.user_id',
            ])
            ->distinct()
            ->limit(50)
            ->get();
    }

    /**
     * 生成 AI 回复后的跟进洞察
     */
    protected function generateFollowUpInsight($conv): ?AiInsight
    {
        // 获取对话上下文（最新几条消息）
        $recentMessages = ConversationMessage::where('conversation_id', $conv->conversation_id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->reverse()
            ->map(fn($m) => ($m->message_type === 'ai_reply' ? 'AI' : '用户') . ': ' . ($m->content ?? ''))
            ->implode("\n");

        // 检查最近是否已有洞察
        $existingInsight = AiInsight::where('conversation_id', $conv->conversation_id)
            ->where('user_id', $conv->user_id)
            ->where('created_at', '>=', now()->subMinutes(config('ai-proactive.min_insight_interval', 120)))
            ->exists();

        if ($existingInsight) {
            return null;
        }

        // 调用 LLM 生成洞察
        $insightData = $this->generateInsightViaLlm($recentMessages);
        if (!$insightData || empty($insightData['should_push'])) {
            return null;
        }

        return AiInsight::create([
            'user_id' => $conv->user_id,
            'tenant_id' => null,
            'conversation_id' => $conv->conversation_id,
            'type' => $insightData['type'] ?? 'follow_up',
            'title' => $insightData['title'] ?? 'AI 主动跟进',
            'content' => $insightData['content'] ?? '您有一条待处理的对话',
            'context' => ['recent_messages' => $recentMessages, 'reason' => $insightData['reason'] ?? ''],
            'status' => 'pending',
            'source' => 'scan_job',
        ]);
    }

    /**
     * 生成客服未回复的跟进洞察
     */
    protected function generateAgentFollowUpInsight($conv): ?AiInsight
    {
        $existingInsight = AiInsight::where('conversation_id', $conv->conversation_id)
            ->where('user_id', $conv->user_id)
            ->where('created_at', '>=', now()->subMinutes(config('ai-proactive.min_insight_interval', 120)))
            ->exists();

        if ($existingInsight) return null;

        return AiInsight::create([
            'user_id' => $conv->user_id,
            'tenant_id' => null,
            'conversation_id' => $conv->conversation_id,
            'type' => 'reminder',
            'title' => '客服待回复',
            'content' => '您有一条消息尚未得到回复，是否需要催促？',
            'context' => ['last_message' => mb_substr($conv->last_user_message ?? '', 0, 200)],
            'status' => 'pending',
            'source' => 'scan_job',
        ]);
    }

    /**
     * 通过 LLM 生成洞察内容
     */
    protected function generateInsightViaLlm(string $conversationContext): ?array
    {
        try {
            $prompt = str_replace(
                '{conversation_context}',
                $conversationContext,
                config('ai-proactive.prompts.generate_insight')
            );

            $response = $this->llm->chat([
                ['role' => 'system', 'content' => '你是一个主动洞察分析助手。只返回合法的 JSON。'],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'model' => config('ai-proactive.llm.model', 'deepseek-chat'),
                'temperature' => config('ai-proactive.llm.temperature', 0.3),
                'max_tokens' => config('ai-proactive.llm.max_tokens', 600),
            ], 'proactive_insight');

            return $this->parseResult($response['content'] ?? '');
        } catch (\Throwable $e) {
            Log::warning('AI 主动洞察 LLM 调用失败', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 推送洞察到用户的通知中心
     */
    protected function deliverInsight(AiInsight $insight): void
    {
        try {
            $this->notifications->send(
                $insight->user_id,
                'ai_insight',
                $insight->title,
                $insight->content,
                [
                    'insight_id' => $insight->id,
                    'type' => $insight->type,
                    'conversation_id' => $insight->conversation_id,
                    'action_url' => $insight->conversation_id ? "/im?conv={$insight->conversation_id}" : null,
                ]
            );
            $insight->markAsSent();
        } catch (\Throwable $e) {
            Log::error('主动洞察推送失败', [
                'insight_id' => $insight->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 检查是否可以向用户推送（每日限额）
     */
    protected function canPushToUser(int $userId): bool
    {
        $dailyLimit = config('ai-proactive.max_daily_per_user', 5);
        $todayCount = AiInsight::byUser($userId)->today()->count();
        return $todayCount < $dailyLimit;
    }

    /**
     * 获取用户的洞察列表
     */
    public function getUserInsights(int $userId, array $filters = [], int $perPage = 20)
    {
        $query = AiInsight::byUser($userId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * 获取洞察统计
     */
    public function getStats(int $userId): array
    {
        $base = AiInsight::byUser($userId);

        return [
            'total' => (clone $base)->count(),
            'unread' => (clone $base)->unread()->count(),
            'today' => (clone $base)->today()->count(),
            'by_type' => (clone $base)->select('type', DB::raw('count(*) as total'))
                ->groupBy('type')->pluck('total', 'type'),
            'by_status' => (clone $base)->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')->pluck('total', 'status'),
            'recent' => (clone $base)->orderBy('created_at', 'desc')->take(5)->get(),
        ];
    }

    /**
     * 标记洞察为已读
     */
    public function markRead(int $id, int $userId): bool
    {
        $insight = AiInsight::where('id', $id)->where('user_id', $userId)->first();
        if (!$insight) return false;

        $insight->markAsRead();
        return true;
    }

    /**
     * 标记洞察为已忽略
     */
    public function dismiss(int $id, int $userId): bool
    {
        $insight = AiInsight::where('id', $id)->where('user_id', $userId)->first();
        if (!$insight) return false;

        $insight->markAsDismissed();
        return true;
    }

    /**
     * 解析 LLM 返回结果
     */
    protected function parseResult(string $raw): ?array
    {
        if (preg_match('/\{[\s\S]*\}/', $raw, $matches)) {
            $data = json_decode($matches[0], true);
            if (!is_array($data)) return null;
            return $data;
        }
        return null;
    }
}
