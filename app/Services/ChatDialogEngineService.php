<?php

namespace App\Services;

use App\Models\License;
use App\Models\RagConversation;
use App\Models\RagMessage;
use App\Services\RagEngineService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AI 智能客服对话引擎
 *
 * 支持：多轮对话上下文管理、意图识别、槽位填充、流式输出、
 * 中英文双语言支持、对话历史存储、满意度评价
 */
class ChatDialogEngineService
{
    public function __construct(
        protected RagEngineService $ragService,
        protected IntentRecognizer $intentRecognizer,
    ) {}

    /**
     * 处理一条用户消息并返回回复
     */
    public function processMessage(
        string $message,
        string $sessionId,
        array $options = []
    ): array {
        $startTime = microtime(true);

        // 1. 获取对话历史
        $history = $this->getHistory($sessionId);
        $conversation = $this->getOrCreateConversation($sessionId);

        // 2. 意图识别
        $intentResult = $this->intentRecognizer->recognize($message, $history);

        // 3. 检查是否需要转人工
        $shouldEscalate = $this->intentRecognizer->shouldEscalate($intentResult);
        if ($shouldEscalate) {
            return $this->handleEscalation($conversation, $message, $intentResult, $startTime);
        }

        // 4. 根据意图分发处理
        $response = match ($intentResult['intent']) {
            'activate_license' => $this->handleActivateLicense($message, $intentResult['entities']),
            'check_license' => $this->handleCheckLicense($intentResult['entities']),
            'device_management' => $this->handleDeviceManagement($message, $intentResult['entities']),
            'renewal' => $this->handleRenewal($intentResult['entities']),
            'trial' => $this->handleTrial(),
            'faq_activation' => $this->handleActivationFAQ($message, $intentResult['entities']),
            'greeting' => $this->handleGreeting(),
            'general_query', 'faq_general', 'billing_info' => $this->handleRagQuery($message, $sessionId, $options),
            default => $this->handleRagQuery($message, $sessionId, $options),
        };

        $responseTime = (microtime(true) - $startTime) * 1000;

        // 5. 保存对话
        $this->saveConversation($conversation, $message, $response, $responseTime);

        return [
            'conversation_id' => $conversation->id,
            'session_id' => $sessionId,
            'intent' => $intentResult['intent'],
            'intent_confidence' => $intentResult['confidence'],
            'answer' => $response['answer'],
            'actions' => $response['actions'] ?? [],
            'sources' => $response['sources'] ?? [],
            'response_time_ms' => round($responseTime, 2),
            'escalated' => false,
        ];
    }

    /**
     * 流式处理消息（支持 SSE 推送）
     * 返回 Generator 用于流式输出
     */
    public function processMessageStreamed(
        string $message,
        string $sessionId,
        array $options = []
    ): \Generator {
        $conversation = $this->getOrCreateConversation($sessionId);

        // 意图识别
        $intentResult = $this->intentRecognizer->recognize($message);
        $shouldEscalate = $this->intentRecognizer->shouldEscalate($intentResult);

        if ($shouldEscalate) {
            yield json_encode(['type' => 'escalate', 'data' => $this->handleEscalation($conversation, $message, $intentResult, 0)]);
            return;
        }

        // 发送意图元数据
        yield json_encode(['type' => 'intent', 'data' => $intentResult]);

        // 构造回答
        $fullResponse = '';

        if ($intentResult['intent'] === 'greeting') {
            $fullResponse = "您好！我是互物通智能客服助手。请问有什么可以帮您的？我可以回答关于 License 激活、设备管理、续费、试用等方面的问题。";
            yield json_encode(['type' => 'chunk', 'data' => $fullResponse]);
        } else {
            // 使用 RAG 检索并流式返回
            try {
                $retrievalResult = $this->ragService->retrieve($message, [
                    'max_results' => 3,
                    'min_confidence' => 0.35,
                ]);

                $documents = $retrievalResult['results'];

                // 尝试用 LLM 生成回答（流式）
                $llmService = app(LlmService::class);
                $context = $this->buildStreamContext($documents);

                $systemPrompt = "你是一个互物通授权管理系统的智能客服助手。基于以下知识库文档回答问题，要求简洁准确。\n\n{$context}";

                $streamResult = $llmService->chatStreamed([
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ]);

                if ($streamResult) {
                    foreach ($streamResult as $chunk) {
                        $fullResponse .= $chunk;
                        yield json_encode(['type' => 'chunk', 'data' => $chunk]);
                    }
                } else {
                    // LLM 不支持流式，使用非流式
                    $result = $this->ragService->answer($message, $sessionId, $options);
                    $fullResponse = $result['answer'];
                    yield json_encode(['type' => 'chunk', 'data' => $fullResponse]);
                    yield json_encode(['type' => 'sources', 'data' => $result['sources'] ?? []]);
                }
            } catch (\Throwable $e) {
                $fallback = $this->ragService->answer($message, $sessionId, $options);
                $fullResponse = $fallback['answer'];
                yield json_encode(['type' => 'chunk', 'data' => $fullResponse]);
            }
        }

        yield json_encode(['type' => 'done', 'data' => ['response_time_ms' => 0]]);

        // 保存完整对话
        $this->saveConversation($conversation, $message, ['answer' => $fullResponse], 0);
    }

    /**
     * 获取对话历史
     */
    public function getHistory(string $sessionId, int $limit = 10): array
    {
        $conversation = RagConversation::where('session_id', $sessionId)->first();
        if (!$conversation) {
            return [];
        }

        return RagMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 记录满意度
     */
    public function recordSatisfaction(int $messageId, bool $satisfied): void
    {
        RagMessage::where('id', $messageId)->update(['was_helpful' => $satisfied]);
    }

    /**
     * 获取统计
     */
    public function getStats(): array
    {
        $totalMessages = RagMessage::count();
        $helpfulCount = RagMessage::where('was_helpful', true)->count();
        $unhelpfulCount = RagMessage::where('was_helpful', false)->count();

        return [
            'total_conversations' => RagConversation::count(),
            'total_messages' => $totalMessages,
            'helpful_count' => $helpfulCount,
            'unhelpful_count' => $unhelpfulCount,
            'satisfaction_rate' => ($helpfulCount + $unhelpfulCount) > 0
                ? round(($helpfulCount / ($helpfulCount + $unhelpfulCount)) * 100, 1)
                : 0,
        ];
    }

    // ─── 意图处理器 ───

    protected function handleActivateLicense(string $message, array $entities): array
    {
        $licenseKey = $entities['license_key'] ?? null;

        $answer = "License 激活流程如下：\n\n";
        $answer .= "1. 确保已获取有效的 License Key\n";
        $answer .= "2. 在您的应用中调用激活 API\n";
        $answer .= "3. 传入 License Key 和设备指纹信息\n\n";

        if ($licenseKey) {
            $license = License::where('license_key', $licenseKey)->first();
            if ($license) {
                $answer .= "您提到的 License Key **{$licenseKey}** 当前状态为：**{$license->status}**\n";
                if ($license->status === 'active') {
                    $answer .= "✅ 该 License 已激活，无需重复激活\n";
                } elseif ($license->is_expired ?? false) {
                    $answer .= "⚠️ 该 License 已过期，请先续费\n";
                }
            }
        }

        $answer .= "\n如需详细的 SDK 接入文档，请参考我们的开发者文档。";

        return [
            'answer' => $answer,
            'actions' => $licenseKey ? [['type' => 'view_license', 'license_key' => $licenseKey]] : [],
            'sources' => [],
        ];
    }

    protected function handleCheckLicense(array $entities): array
    {
        $licenseKey = $entities['license_key'] ?? null;

        if (!$licenseKey) {
            return [
                'answer' => '请提供您的 License Key 以便查询状态。License Key 格式如：HWT-XXXXXXXX-XXXXXXXX',
                'actions' => [],
                'sources' => [],
            ];
        }

        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return [
                'answer' => "未找到 License Key **{$licenseKey}**，请检查输入是否正确。",
                'actions' => [],
                'sources' => [],
            ];
        }

        $answer = "**License 状态查询结果**\n\n";
        $answer .= "- Key: `{$license->license_key}`\n";
        $answer .= "- 类型: {$license->type}\n";
        $answer .= "- 状态: **{$license->status}**\n";
        $answer .= "- 最大设备数: {$license->max_devices}\n";

        if ($license->expires_at) {
            $daysLeft = now()->diffInDays($license->expires_at, false);
            if ($daysLeft > 0) {
                $answer .= "- 有效期至: {$license->expires_at->toDateString()}（还剩 {$daysLeft} 天）\n";
            } else {
                $answer .= "- ⚠️ 有效期至: {$license->expires_at->toDateString()}（已过期）\n";
            }
        }

        return [
            'answer' => $answer,
            'actions' => [['type' => 'view_license', 'license_key' => $licenseKey]],
            'sources' => [],
        ];
    }

    protected function handleDeviceManagement(string $message, array $entities): array
    {
        $licenseKey = $entities['license_key'] ?? null;

        $answer = "**设备管理说明**\n\n";
        $answer .= "1. 查看已绑定设备：登录管理后台 → 设备管理\n";
        $answer .= "2. 解绑设备：在旧设备上执行解绑操作，或在管理后台强制踢出\n";
        $answer .= "3. 设备数量限制：每个 License 可绑定一定数量的设备\n";

        if ($licenseKey) {
            $devices = Device::whereHas('activation', fn($q) => $q->whereHas('license', fn($q) => $q->where('license_key', $licenseKey)))
                ->get();
            $answer .= "\nLicense **{$licenseKey}** 当前绑定设备数：{$devices->count()} 台\n";
        }

        return [
            'answer' => $answer,
            'actions' => [['type' => 'view_devices']],
            'sources' => [],
        ];
    }

    protected function handleRenewal(array $entities): array
    {
        return [
            'answer' => "**续费流程**\n\n1. 登录管理后台\n2. 进入订阅管理\n3. 找到需要续期的 License\n4. 点击续费按钮\n5. 选择续费周期并完成支付\n\n续费后 License 将自动延长有效期。",
            'actions' => [],
            'sources' => [],
        ];
    }

    protected function handleTrial(): array
    {
        return [
            'answer' => "**免费试用说明**\n\n我们提供 14 天免费试用，无需绑定支付方式。\n\n试用流程：\n1. 注册账号\n2. 选择产品并申请试用\n3. 获取试用 License Key\n4. 集成 SDK 开始使用\n\n试用到期后可以选择购买正式 License 继续使用。",
            'actions' => [['type' => 'start_trial']],
            'sources' => [],
        ];
    }

    protected function handleActivationFAQ(string $message, array $entities): array
    {
        $errorCode = $entities['error_code'] ?? null;

        $answer = "**激活失败常见原因**\n\n";
        $answer .= "1. ⚠️ License Key 不存在或已过期\n";
        $answer .= "2. ⚠️ 设备数量已达上限\n";
        $answer .= "3. ⚠️ 设备在黑名单中\n";
        $answer .= "4. ⚠️ 网络连接异常\n";
        $answer .= "5. ⚠️ 请求过于频繁被限流\n\n";

        if ($errorCode) {
            try {
                $diagnostic = app(DiagnosticEngineService::class)->diagnose($errorCode);
                $answer .= "针对错误码 **{$errorCode}** 的详细分析：\n";
                $answer .= "- {$diagnostic['summary']}\n";
                $answer .= "- {$diagnostic['detail']}\n\n";
                $answer .= "建议操作：\n";
                foreach ($diagnostic['suggestions'] as $i => $suggestion) {
                    $answer .= ($i + 1) . ". {$suggestion}\n";
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $answer .= "\n如果问题仍然无法解决，请联系技术支持。";

        return [
            'answer' => $answer,
            'actions' => $errorCode ? [['type' => 'diagnose', 'error_code' => $errorCode]] : [],
            'sources' => [],
        ];
    }

    protected function handleGreeting(): array
    {
        return [
            'answer' => "您好！我是互物通智能客服助手 🤖\n\n我可以帮您处理以下问题：\n" .
                "- 🔑 **激活与授权**：激活 License、查询状态、延期\n" .
                "- 💻 **设备管理**：解绑设备、查询设备列表\n" .
                "- 💰 **订阅与计费**：续费、套餐变更、发票查询\n" .
                "- ❓ **常见问题**：激活失败排查、使用指南\n\n" .
                "请直接输入您的问题，我会尽力帮您解答！",
            'actions' => [],
            'sources' => [],
        ];
    }

    protected function handleRagQuery(string $message, string $sessionId, array $options): array
    {
        $result = $this->ragService->answer($message, $sessionId, $options);

        return [
            'answer' => $result['answer'],
            'sources' => $result['sources'] ?? [],
            'actions' => [],
        ];
    }

    /**
     * 处理转人工（创建工单）
     */
    protected function handleEscalation(RagConversation $conversation, string $message, array $intentResult, float $startTime): array
    {
        $ticketId = null;

        // 创建一个工单
        try {
            $ticketService = app(TicketService::class);
            $ticket = $ticketService->create([
                'subject' => '在线客服转人工: ' . Str::limit($message, 100),
                'description' => "对话来源: 在线客服\n\n" .
                    "用户问题: {$message}\n\n" .
                    "对话上下文: " . json_encode($this->getHistory($conversation->session_id, 3), JSON_UNESCAPED_UNICODE),
                'priority' => 'medium',
                'source' => 'chat',
                'metadata' => [
                    'session_id' => $conversation->session_id,
                    'conversation_id' => $conversation->id,
                    'intent' => $intentResult['intent'] ?? 'unknown',
                ],
            ]);
            $ticketId = $ticket->id;
        } catch (\Throwable $e) {
            Log::error('LiveChat: failed to create ticket for escalation', [
                'error' => $e->getMessage(),
            ]);
        }

        $answer = "您已要求转接人工客服，我们会尽快为您安排。\n\n";
        if ($ticketId) {
            $answer .= "📋 已自动创建工单 #{$ticketId}，客服将尽快回复您。\n\n";
        }

        $response = [
            'answer' => $answer .
                "预计等待时间：1-5 分钟\n" .
                "工作时间：周一至周五 9:00-18:00\n\n" .
                "紧急情况请拨打：400-000-0000",
            'actions' => [['type' => 'escalate_to_human', 'ticket_id' => $ticketId]],
            'sources' => [],
        ];

        $responseTime = (microtime(true) - $startTime) * 1000;
        $this->saveConversation($conversation, $message, $response, $responseTime);

        return [
            'conversation_id' => $conversation->id,
            'session_id' => $conversation->session_id,
            'intent' => 'contact_human',
            'intent_confidence' => $intentResult['confidence'] ?? 1.0,
            'answer' => $response['answer'],
            'actions' => $response['actions'],
            'response_time_ms' => round($responseTime, 2),
            'escalated' => true,
        ];
    }

    /**
     * 获取或创建对话
     */
    protected function getOrCreateConversation(string $sessionId): RagConversation
    {
        $conversation = RagConversation::where('session_id', $sessionId)->first();
        if (!$conversation) {
            $conversation = RagConversation::create([
                'session_id' => $sessionId,
                'locale' => 'zh-CN',
            ]);
        }
        return $conversation;
    }

    /**
     * 保存对话
     */
    protected function saveConversation(RagConversation $conversation, string $message, array $response, float $responseTime): void
    {
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response['answer'],
            'source_documents' => $response['sources'] ?? [],
            'response_time_ms' => $responseTime,
        ]);

        // 更新标题
        if ($conversation->messages()->count() <= 2) {
            $conversation->update(['title' => Str::limit($message, 100)]);
        }
    }

    /**
     * 构建流式上下文
     */
    protected function buildStreamContext(array $documents): string
    {
        if (empty($documents)) {
            return '未找到相关文档，请基于你的知识回答。';
        }

        $context = '';
        foreach ($documents as $i => $doc) {
            $context .= "[文档 {$i}] {$doc['title']}: {$doc['content']}\n";
        }
        return $context;
    }
}
