<?php

namespace App\Services;

use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Log;

class AiMessageReviewService
{
    protected LlmService $llm;
    protected SensitiveWordService $sensitiveWord;
    protected PromptTemplateService $promptTemplates;

    public function __construct(
        LlmService $llm,
        SensitiveWordService $sensitiveWord,
        PromptTemplateService $promptTemplates,
    ) {
        $this->llm = $llm;
        $this->sensitiveWord = $sensitiveWord;
        $this->promptTemplates = $promptTemplates;
    }

    /**
     * 对消息进行发送前预审
     *
     * @return array{pass:bool, level:string, warnings:array, suggestion:string, categories:array, review_id:string}
     */
    public function review(string $content, array $context = []): array
    {
        $reviewId = 'rev_' . uniqid();
        $warnings = [];
        $categories = [];
        $level = 'low';

        // ── 第一道防线：敏感词检查 ──
        if (config('ai-message-review.sensitive_word_check.enabled', true)) {
            $sensitiveResult = $this->sensitiveWord->check($content);
            if ($sensitiveResult['hasSensitive']) {
                $matchedWords = $sensitiveResult['matched'];
                foreach ($matchedWords as $word) {
                    $hint = str_replace(
                        '{word}',
                        $word,
                        config('ai-message-review.sensitive_word_check.replacement_hint', '消息包含敏感词「{word}」，请修改后重试')
                    );
                    $warnings[] = $hint;
                }
                $categories[] = 'sensitive';
                $level = $this->maxLevel($level, config('ai-message-review.sensitive_word_check.default_level', 'high'));
            }
        }

        // ── 第二道防线：LLM 语义审查（仅对较长消息执行） ──
        $minLen = config('ai-message-review.min_length_for_llm', 10);
        if (mb_strlen($content) >= $minLen) {
            $llmResult = $this->reviewViaLlm($content, $context);
            if ($llmResult && !$llmResult['pass']) {
                $level = $this->maxLevel($level, $llmResult['level'] ?? 'medium');
                $warnings = array_merge($warnings, $llmResult['warnings'] ?? []);
                $categories = array_merge($categories, $llmResult['categories'] ?? []);
            }
        }

        // ── 确定最终动作 ──
        $action = $this->levelToAction($level);
        $pass = $action !== 'block';

        $result = [
            'pass' => $pass,
            'level' => $level,
            'action' => $action,
            'warnings' => array_unique($warnings),
            'suggestion' => $this->buildSuggestion($warnings, $categories),
            'categories' => array_unique($categories),
            'review_id' => $reviewId,
        ];

        // ── 日志 ──
        Log::debug('AI 消息预审完成', [
            'review_id' => $reviewId,
            'level' => $level,
            'action' => $action,
            'pass' => $pass,
            'warnings_count' => count($warnings),
            'content_preview' => mb_substr($content, 0, 100),
        ]);

        return $result;
    }

    /**
     * 通过 LLM 进行语义审查
     */
    protected function reviewViaLlm(string $content, array $context = []): ?array
    {
        try {
            // 使用 PromptTemplate 或默认 prompt
            $systemPrompt = $this->promptTemplates->renderByCategory('message_review', [], 'review_system');
            if (empty($systemPrompt)) {
                $systemPrompt = config('ai-message-review.prompts.review_system');
            }

            $userPromptTemplate = $this->promptTemplates->renderByCategory('message_review', ['message' => $content], 'review_user');
            if (empty($userPromptTemplate)) {
                $userPromptTemplate = str_replace(
                    '{message}',
                    $content,
                    config('ai-message-review.prompts.review_user')
                );
            }

            $response = $this->llm->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPromptTemplate],
            ], [
                'model' => config('ai-message-review.llm.model', 'deepseek-chat'),
                'temperature' => config('ai-message-review.llm.temperature', 0.1),
                'max_tokens' => config('ai-message-review.llm.max_tokens', 500),
            ], 'message_review');

            return $this->parseReviewResult($response['content'] ?? '');
        } catch (\Throwable $e) {
            Log::warning('AI 消息预审 LLM 调用失败', [
                'error' => $e->getMessage(),
                'content' => mb_substr($content, 0, 100),
            ]);
            // LLM 失败时放行（降级）
            return [
                'pass' => true,
                'level' => 'low',
                'warnings' => [],
                'categories' => [],
                'suggestion' => '',
            ];
        }
    }

    /**
     * 解析 LLM 返回的 JSON 结果
     */
    protected function parseReviewResult(string $raw): ?array
    {
        if (preg_match('/\{[\s\S]*\}/', $raw, $matches)) {
            $json = $matches[0];
        } else {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return [
            'pass' => (bool)($data['pass'] ?? true),
            'level' => in_array($data['level'] ?? '', ['high', 'medium', 'low']) ? $data['level'] : 'low',
            'warnings' => is_array($data['warnings'] ?? null) ? $data['warnings'] : [],
            'categories' => is_array($data['categories'] ?? null) ? $data['categories'] : [],
            'suggestion' => (string)($data['suggestion'] ?? ''),
        ];
    }

    /**
     * 将审查结果写入消息 metadata
     */
    public function attachToMessage(ConversationMessage $message, array $reviewResult): void
    {
        $metadata = $message->metadata ?? [];
        $metadata['ai_review'] = [
            'review_id' => $reviewResult['review_id'],
            'level' => $reviewResult['level'],
            'action' => $reviewResult['action'],
            'pass' => $reviewResult['pass'],
            'warnings' => $reviewResult['warnings'],
            'categories' => $reviewResult['categories'],
            'reviewed_at' => now()->toDateTimeString(),
        ];
        $message->metadata = $metadata;
        $message->saveQuietly();
    }

    /**
     * 快速检查（供前端实时调用）
     */
    public function quickCheck(string $content): array
    {
        $warnings = [];
        $hasIssue = false;

        // 敏感词快速检查
        if (config('ai-message-review.sensitive_word_check.enabled', true)) {
            $result = $this->sensitiveWord->check($content);
            if ($result['hasSensitive']) {
                $hasIssue = true;
                foreach ($result['matched'] as $word) {
                    $warnings[] = str_replace(
                        '{word}', $word,
                        config('ai-message-review.sensitive_word_check.replacement_hint', '消息包含敏感词「{word}」')
                    );
                }
            }
        }

        return [
            'has_issue' => $hasIssue,
            'warnings' => $warnings,
            'sensitive_replaced' => $result['replaced'] ?? $content,
        ];
    }

    // ── 辅助 ──

    protected function maxLevel(string $a, string $b): string
    {
        $map = ['low' => 0, 'medium' => 1, 'high' => 2];
        return ($map[$b] ?? 0) > ($map[$a] ?? 0) ? $b : $a;
    }

    protected function levelToAction(string $level): string
    {
        return config("ai-message-review.levels.{$level}.action", 'log');
    }

    protected function buildSuggestion(array $warnings, array $categories): string
    {
        if (empty($warnings)) return '';

        if (in_array('leakage', $categories)) {
            return '消息可能包含敏感信息，请移除密码、密钥等敏感内容后重试。';
        }
        if (in_array('tone', $categories)) {
            return '请调整语气，保持专业礼貌的沟通方式。';
        }
        if (in_array('harassment', $categories)) {
            return '消息包含不当言论，请尊重他人。';
        }
        if (in_array('sensitive', $categories)) {
            return '消息包含敏感词汇，请修改后重试。';
        }

        return '消息存在风险，建议修改后发送。';
    }
}
