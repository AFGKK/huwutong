<?php

namespace App\Services;

use App\Models\AiMemory;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MemoryExtractionService
{
    protected LlmService $llm;
    protected MemoryService $memory;

    protected const EXTRACTION_PROMPT = <<<'PROMPT'
你是一个专业的长期记忆提取助手。请分析以下对话内容，提取出值得记住的**用户信息**。

请严格以 JSON 数组格式返回，每个元素包含：
- `content`: 记忆内容（一句话，简洁完整）
- `type`: 类型 (preference=偏好, fact=事实, context=上下文, insight=洞察, behavior=行为)
- `category`: 分类 (user_preference, user_fact, business_info, technical_context, project_context, conversation_style)
- `confidence`: 置信度 (0-1 浮点数)
- `priority`: 优先级 (0-255 整数，越高越重要)

规则：
1. **只提取值得长期记住的信息**（个人偏好、重要事实、业务上下文等）
2. **不要提取**：问候语、临时状态、敏感信息（密码/密钥）、明显一次性的对话
3. 如果没有值得提取的信息，返回空数组 []
4. 每条记忆必须是对用户有长期价值的信息
5. 使用中文输出

对话内容：
{conversation_text}

JSON:
PROMPT;

    public function __construct(LlmService $llm, MemoryService $memory)
    {
        $this->llm = $llm;
        $this->memory = $memory;
    }

    /**
     * 从一段对话文本中提取记忆
     */
    public function extractFromText(string $text, int $userId, ?int $tenantId = null): array
    {
        if (mb_strlen(trim($text)) < 20) {
            return [];
        }

        $prompt = str_replace('{conversation_text}', $text, self::EXTRACTION_PROMPT);

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => '你是一个记忆提取助手，只返回合法的 JSON 数组。'],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'model' => config('ai-memory.extraction.model', 'deepseek-chat'),
                'temperature' => 0.1,
                'max_tokens' => 2000,
            ], 'memory_extract');

            $extracted = $this->parseResult($result['content'] ?? '');

            if (empty($extracted)) {
                return [];
            }

            $stored = [];
            foreach ($extracted as $item) {
                try {
                    $memory = $this->memory->store(
                        userId: $userId,
                        key: 'ai_extracted_' . uniqid(),
                        content: $item['content'],
                        type: $item['type'] ?? 'fact',
                        source: 'ai_extracted',
                        confidence: min(1.0, (float)($item['confidence'] ?? 0.7)),
                        priority: (int)($item['priority'] ?? 0),
                        category: $item['category'] ?? null,
                        tags: ['ai_extracted'],
                        tenantId: $tenantId,
                    );
                    $stored[] = $memory;
                } catch (\Throwable $e) {
                    Log::warning('记忆提取存储失败', [
                        'error' => $e->getMessage(),
                        'item' => $item,
                    ]);
                }
            }

            return $stored;
        } catch (\Throwable $e) {
            Log::warning('AI记忆提取调用失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return [];
        }
    }

    /**
     * 从对话消息中提取记忆（自动获取最近N条消息）
     */
    public function extractFromConversation(int $conversationId, int $userId, int $messageCount = 20, ?int $tenantId = null): array
    {
        $messages = ConversationMessage::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take($messageCount)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return [];
        }

        $text = $messages->map(fn ($m) => ($m->user_id === $userId ? '用户' : 'AI') . ': ' . ($m->content ?? ''))
            ->implode("\n");

        return $this->extractFromText($text, $userId, $tenantId);
    }

    /**
     * 解析 LLM 返回的 JSON
     */
    protected function parseResult(string $raw): array
    {
        // 尝试提取 JSON 数组
        if (preg_match('/\[[\s\S]*\]/', $raw, $matches)) {
            $json = $matches[0];
        } else {
            return [];
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [];
        }

        // 过滤并验证
        return array_filter($data, function ($item) {
            return isset($item['content']) && is_string($item['content']) && mb_strlen($item['content']) > 5;
        });
    }
}
