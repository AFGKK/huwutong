<?php

namespace App\Services;

use App\Models\AiFriendProfile;
use App\Models\AiFriendLlmConfig;
use App\Models\ConversationMessage;
use App\Models\UserConversation;
use App\Models\ConversationParticipant;
use App\Services\LlmService;
use App\Services\MemoryService;
use App\Services\PromptFirewallService;
use App\Services\HallucinationDetector;
use App\Services\ContentSignatureService;
use Illuminate\Support\Facades\Log;

/**
 * AI 好友对话编排器 (AIF-004)
 *
 * 收消息 → 组上下文 → 调 LLM → 流式回发
 */
class AiFriendOrchestrator
{
    protected LlmService $llm;
    protected MemoryService $memory;
    protected PromptFirewallService $firewall;
    protected HallucinationDetector $hallucination;
    protected ContentSignatureService $signer;
    protected ?AiFriendProfile $profile = null;
    protected ?AiFriendLlmConfig $config = null;

    public function __construct(LlmService $llm, MemoryService $memory, PromptFirewallService $firewall, HallucinationDetector $hallucination, ContentSignatureService $signer)
    {
        $this->llm = $llm;
        $this->memory = $memory;
        $this->firewall = $firewall;
        $this->hallucination = $hallucination;
        $this->signer = $signer;
    }

    public function forFriend(AiFriendProfile $profile): self
    {
        $this->profile = $profile;
        $this->config = $profile->llmConfig;
        return $this;
    }

    /**
     * 构建对话上下文（含长期记忆注入）
     */
    public function buildContext(int $convId, string $userMessage): array
    {
        // 基础 system prompt
        $system = $this->config->system_prompt ?? '你是一个友好的 AI 助手。';

        // 注入用户长期记忆
        $humanUserId = $this->getHumanUserId($convId);
        if ($humanUserId) {
            $memories = $this->memory->getContextForUser($humanUserId, max: 8);
            if ($memories) {
                $system .= "\n\n{$memories}";
            }
        }

        $messages = [['role' => 'system', 'content' => $system]];

        // 拉取最近 N 轮上下文
        $contextWindow = $this->config->context_window ?? 20;
        $recent = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->take($contextWindow)
            ->get()
            ->reverse();

        foreach ($recent as $msg) {
            $role = $msg->message_type === 'ai_reply' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => $msg->content];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    /**
     * 获取会话中的人类用户 ID（排除 AI 好友自身）
     */
    protected function getHumanUserId(int $convId): ?int
    {
        $aiUserId = $this->profile?->user_id;
        $participant = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', '!=', $aiUserId)
            ->first();
        return $participant?->user_id;
    }

    /**
     * 生成回复（流式）
     */
    public function generateStream(int $convId, string $userMessage): \Generator
    {
        if (!$this->profile || !$this->config) {
            throw new \RuntimeException('AI 好友未配置');
        }

        $contextMessages = $this->buildContext($convId, $userMessage);

        // 适配 LLM Gateway
        $options = [
            'temperature' => $this->config->temperature ?? 0.7,
            'max_tokens' => $this->config->max_tokens ?? 2048,
            'model' => $this->config->model_name ?? 'deepseek-chat',
        ];

        if ($this->config->api_base_url) {
            $options['api_base'] = $this->config->api_base_url;
        }

        $stream = $this->llm->chatStream($contextMessages, $options);
        return $stream;
    }

    /**
     * 生成回复（非流式）—— 含预审 + 记忆提取
     */
    public function generate(int $convId, string $userMessage): array
    {
        if (!$this->profile || !$this->config) {
            throw new \RuntimeException('AI 好友未配置');
        }

        // AI-044: 用户消息预审（Prompt 防火墙 + 敏感词）
        $preCheck = $this->firewall->inspect($userMessage);
        if ($preCheck['blocked']) {
            return [
                'content' => '抱歉，您的消息包含不安全内容，已被系统拦截。',
                'pre_check' => $preCheck,
            ];
        }

        $contextMessages = $this->buildContext($convId, $userMessage);

        $options = [
            'temperature' => $this->config->temperature ?? 0.7,
            'max_tokens' => $this->config->max_tokens ?? 2048,
            'model' => $this->config->model_name ?? 'deepseek-chat',
        ];

        if ($this->config->api_base_url) {
            $options['api_base'] = $this->config->api_base_url;
        }

        $result = $this->llm->chat($contextMessages, $options, 'ai_friend');

        // AI-044: AI 回复内容预审
        $replyContent = $result['content'] ?? '';
        $replyCheck = $this->firewall->inspect($replyContent);
        if ($replyCheck['blocked']) {
            $result['content'] = '抱歉，AI 生成的回复触发了安全策略，已过滤。';
            $result['pre_check'] = $replyCheck;
        }

        // AI-043: 自动提取记忆（从用户消息中提取关键信息）
        try {
            $this->extractMemoriesFromMessage($convId, $userMessage);
        } catch (\Throwable $e) {
            Log::warning('[AiFriend] memory extraction failed: ' . $e->getMessage());
        }

        // 幻觉检测：对 AI 回复做事实校验
        $replyContent = $result['content'] ?? '';
        if (mb_strlen($replyContent) > 50 && !($replyCheck['blocked'] ?? false)) {
            try {
                $hcResult = $this->hallucination->annotate($replyContent);
                $result['content'] = $hcResult['content'];
                $result['hallucination_check'] = $hcResult['check'];
            } catch (\Throwable $e) {
                Log::warning('[AiFriend] hallucination check failed: ' . $e->getMessage());
            }
        }

        // 内容溯源：为 AI 回复添加数字签名
        $finalContent = $result['content'] ?? $replyContent;
        if (mb_strlen($finalContent) > 20) {
            try {
                $sigResult = $this->signer->appendSignatureMark($finalContent, 'ai_reply', $convId);
                $result['content'] = $sigResult['content'];
                $result['content_signature'] = $sigResult['signature'];
            } catch (\Throwable $e) {
                Log::warning('[AiFriend] content signature failed: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * 从用户消息中提取关键记忆
     */
    protected function extractMemoriesFromMessage(int $convId, string $message): void
    {
        $humanUserId = $this->getHumanUserId($convId);
        if (!$humanUserId) return;

        // 简单模式：提取包含个人偏好的陈述
        $patterns = [
            '/我(?:喜欢|爱|欣赏|偏爱|推荐?荐)\s*(.+)/u',
            '/我(?:的?名字?是|叫)\s*(.+)/u',
            '/我(?:在|从事|做)\s*(.+?)(?:工作|行业|公司)/u',
            '/我(?:住|在|位于)\s*(.+)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $content = trim($matches[1] ?? '');
                if ($content && mb_strlen($content) > 2) {
                    $this->memory->store(
                        userId: $humanUserId,
                        key: 'extracted_' . md5($content),
                        content: $content,
                        type: 'fact',
                        source: 'conversation',
                        confidence: 0.6,
                        category: 'personal',
                    );
                    Log::info("[AiFriend] 自动提取记忆: {$content}");
                }
            }
        }
    }

    /**
     * 测试 AI 好友连通性
     */
    public function testConnection(): array
    {
        if (!$this->profile || !$this->config) {
            return ['success' => false, 'message' => 'AI 好友未配置'];
        }

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => $this->config->system_prompt ?? '你是一个 AI 助手。'],
                ['role' => 'user', 'content' => '回复"连接成功"即可，不要多余内容。'],
            ], [
                'temperature' => 0.1,
                'max_tokens' => 20,
                'model' => $this->config->model_name ?? 'deepseek-chat',
            ], 'ai_friend_test');

            $content = $result['content'] ?? '';
            return [
                'success' => true,
                'message' => '连接成功: ' . mb_substr($content, 0, 100),
                'usage' => $result['usage'] ?? [],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => '连接失败: ' . $e->getMessage(),
            ];
        }
    }
}
