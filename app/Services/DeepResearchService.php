<?php

namespace App\Services;

use App\Models\DeepResearchTask;
use App\Models\ConversationMessage;
use App\Services\LlmService;
use App\Services\RagEngineService;
use App\Services\KnowledgeBaseService;
use Illuminate\Support\Facades\Log;

/**
 * AI 深度研究模式
 *
 * 对标 Perplexity Deep Research：
 * 1. 问题拆解 → 2. 多源检索 → 3. 多轮深化 → 4. 结构化报告
 */
class DeepResearchService
{
    protected LlmService $llm;
    protected RagEngineService $rag;
    protected KnowledgeBaseService $kb;

    public function __construct(LlmService $llm, RagEngineService $rag, KnowledgeBaseService $kb)
    {
        $this->llm = $llm;
        $this->rag = $rag;
        $this->kb = $kb;
    }

    /**
     * 启动一项深度研究任务
     */
    public function start(int $userId, string $query): DeepResearchTask
    {
        $task = DeepResearchTask::create([
            'user_id' => $userId,
            'query' => $query,
            'status' => 'in_progress',
            'progress' => 0,
        ]);

        try {
            $this->execute($task);
        } catch (\Throwable $e) {
            $task->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'progress' => 100,
            ]);
            Log::error("[DeepResearch] 执行失败: " . $e->getMessage());
        }

        return $task->fresh();
    }

    /**
     * 执行完整研究管道（同步）
     */
    protected function execute(DeepResearchTask $task): void
    {
        $query = $task->query;

        // ─── Step 1: 问题拆解 ───
        $task->update(['progress' => 10, 'sub_questions' => []]);
        $subQuestions = $this->decomposeQuestion($query);
        $task->update(['sub_questions' => $subQuestions, 'progress' => 20]);

        // ─── Step 2: 多源检索 ───
        $allFindings = [];
        $total = count($subQuestions);
        $sources = [];

        foreach ($subQuestions as $i => $sq) {
            $stepProgress = 20 + (int)(($i / max($total, 1)) * 50);
            $task->update(['progress' => $stepProgress]);

            $findingsForSQ = $this->searchSources($sq);
            $allFindings[] = [
                'question' => $sq,
                'findings' => $findingsForSQ,
            ];

            // 收集来源
            foreach ($findingsForSQ as $f) {
                if (!empty($f['source'])) {
                    $sources[] = $f['source'];
                }
            }
        }

        $task->update([
            'findings' => $allFindings,
            'source_count' => (string) count(array_unique($sources)),
            'progress' => 70,
        ]);

        // ─── Step 3: 报告生成 ───
        $task->update(['progress' => 80]);
        $report = $this->generateReport($query, $subQuestions, $allFindings, $sources);
        $totalTokens = $report['total_tokens'] ?? 0;

        // ─── Step 4: 完成 ───
        $task->update([
            'report' => $report['content'] ?? '',
            'status' => 'completed',
            'total_tokens' => $totalTokens,
            'progress' => 100,
        ]);
    }

    // ═══════════════════════════════════════════
    //  Step 1: 问题拆解
    // ═══════════════════════════════════════════

    /**
     * 将复杂问题拆解为 3-5 个子问题
     */
    protected function decomposeQuestion(string $query): array
    {
        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => implode("\n", [
                    '你是一个研究分析师。将以下研究问题拆解为 3-5 个关键子问题。',
                    '子问题应覆盖：背景/定义、现状/数据、原因/分析、影响/趋势、解决方案/建议。',
                    '返回 JSON 数组，如：["子问题1", "子问题2", "子问题3"]',
                    '只返回 JSON，不要其他内容。',
                ])],
                ['role' => 'user', 'content' => "研究问题：{$query}"],
            ], [
                'temperature' => 0.3,
                'max_tokens' => 1000,
                'model' => 'deepseek-chat',
            ], 'deep_research_decompose');

            $reply = $result['content'] ?? '[]';
            if (preg_match('/\[.*\]/s', $reply, $m)) {
                $questions = json_decode($m[0], true);
                if (is_array($questions) && count($questions) > 0) {
                    return array_slice($questions, 0, 5);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[DeepResearch] 拆解失败: " . $e->getMessage());
        }

        // 降级：以原问题本身作为唯一子问题
        return [$query];
    }

    // ═══════════════════════════════════════════
    //  Step 2: 多源检索
    // ═══════════════════════════════════════════

    /**
     * 对单个子问题进行多源检索
     *
     * @return array{source: string, content: string, confidence: float}[]
     */
    protected function searchSources(string $question): array
    {
        $findings = [];

        // 来源1: RAG 知识库
        try {
            $ragResults = $this->rag->retrieve($question, ['limit' => 3]);
            foreach ($ragResults['results'] ?? [] as $r) {
                $findings[] = [
                    'source' => '知识库: ' . ($r['title'] ?? '未知'),
                    'content' => $r['content'] ?? '',
                    'confidence' => $r['score'] ?? 0.5,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("[DeepResearch] RAG 检索失败: " . $e->getMessage());
        }

        // 来源2: KB 文章搜索
        try {
            $kbResults = $this->kb->searchArticles($question, ['limit' => 3]);
            foreach ($kbResults['results'] ?? $kbResults as $r) {
                $title = is_array($r) ? ($r['title'] ?? '') : ($r->title ?? '');
                $content = is_array($r) ? ($r['content'] ?? '') : ($r->content ?? '');
                if ($title && $content) {
                    $findings[] = [
                        'source' => "帮助中心: {$title}",
                        'content' => mb_substr($content, 0, 2000),
                        'confidence' => 0.6,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[DeepResearch] KB 搜索失败: " . $e->getMessage());
        }

        // 来源3: AI 客服对话中的相关问答
        try {
            $ragAnswer = $this->rag->answer($question);
            if (!empty($ragAnswer['answer']) && ($ragAnswer['confidence'] ?? 0) > 0.3) {
                $findings[] = [
                    'source' => 'AI 客服知识',
                    'content' => mb_substr($ragAnswer['answer'], 0, 1500),
                    'confidence' => $ragAnswer['confidence'] ?? 0.5,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("[DeepResearch] RAG 问答失败: " . $e->getMessage());
        }

        // 来源4: LLM 自身知识（通过直接问答获取）
        try {
            $llmResult = $this->llm->chat([
                ['role' => 'system', 'content' => '你是一个知识渊博的研究助手。请基于你的训练知识回答以下问题，答案要全面、准确、有数据支撑。如果不确定，请注明。'],
                ['role' => 'user', 'content' => $question],
            ], [
                'temperature' => 0.3,
                'max_tokens' => 2000,
                'model' => 'deepseek-chat',
            ], 'deep_research_llm');

            $answer = $llmResult['content'] ?? '';
            if ($answer && mb_strlen($answer) > 50) {
                $findings[] = [
                    'source' => 'AI 模型知识',
                    'content' => $answer,
                    'confidence' => 0.7,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("[DeepResearch] LLM 问答失败: " . $e->getMessage());
        }

        return $findings;
    }

    // ═══════════════════════════════════════════
    //  Step 3: 报告生成
    // ═══════════════════════════════════════════

    /**
     * 综合所有发现生成结构化研究报告
     */
    protected function generateReport(string $query, array $subQuestions, array $allFindings, array $sources): array
    {
        // 构建研究发现摘要
        $findingsText = '';
        foreach ($allFindings as $i => $item) {
            $findingsText .= "\n## 子问题 {$i}: {$item['question']}\n";
            foreach ($item['findings'] as $j => $f) {
                $findingsText .= "\n来源 {$j}: {$f['source']}\n{$f['content']}\n";
            }
        }

        $sourcesText = implode("\n", array_unique(array_filter($sources)));

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => implode("\n", [
                    '你是一个专业研究分析师。基于以下研究发现，生成一份结构化的研究报告。',
                    '要求：',
                    '- 标题：简洁概括研究主题',
                    '- 摘要：100-200 字概述',
                    '- 正文：按子问题分章节，每章包含分析、数据、结论',
                    '- 结论：综合所有发现，给出核心洞察和建议',
                    '- 参考来源：列出引用的来源',
                    '格式：Markdown，适合直接阅读。',
                ])],
                ['role' => 'user', 'content' => "研究问题：{$query}\n\n研究发现：\n{$findingsText}\n\n参考来源：\n{$sourcesText}"],
            ], [
                'temperature' => 0.3,
                'max_tokens' => 4000,
                'model' => 'deepseek-chat',
            ], 'deep_research_report');

            $content = $result['content'] ?? '';
            $usage = $result['usage'] ?? [];

            return [
                'content' => $content,
                    'total_tokens' => $usage['total_tokens'] ?? 0,
            ];
        } catch (\Throwable $e) {
            Log::error("[DeepResearch] 报告生成失败: " . $e->getMessage());

            // 降级：简单拼接
            $fallback = "# 研究报告：{$query}\n\n";
            foreach ($allFindings as $item) {
                $fallback .= "\n## {$item['question']}\n";
                foreach ($item['findings'] as $f) {
                    $fallback .= "\n{$f['content']}\n";
                }
            }
            return ['content' => $fallback, 'total_tokens' => 0];
        }
    }

    // ═══════════════════════════════════════════
    //  查询接口
    // ═══════════════════════════════════════════

    /**
     * 获取用户的研究历史
     */
    public function getUserTasks(int $userId, int $perPage = 20)
    {
        return DeepResearchTask::byUser($userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * 获取研究详情
     */
    public function getTaskDetail(int $id, int $userId): ?DeepResearchTask
    {
        return DeepResearchTask::byUser($userId)->find($id);
    }
}
