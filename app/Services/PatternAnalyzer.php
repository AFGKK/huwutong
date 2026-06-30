<?php

namespace App\Services;

use App\Models\AiInteractionLog;
use App\Models\SelfLearningPattern;
use App\Models\AiFriendProfile;
use App\Models\AiFriendLlmConfig;
use App\Services\LlmService;
use Illuminate\Support\Facades\Log;

/**
 * 模式分析器
 *
 * 分析 AI 交互日志，提取优化模式和建议。
 * 核心学习引擎——发现"什么有效、什么无效"。
 */
class PatternAnalyzer
{
    protected LlmService $llm;

    public function __construct(LlmService $llm)
    {
        $this->llm = $llm;
    }

    /**
     * 运行一次完整的模式分析周期
     *
     * @return array{patterns_found: int, by_type: array}
     */
    public function analyze(int $lookbackHours = 24): array
    {
        $results = ['patterns_found' => 0, 'by_type' => []];

        // 分析1: 低质量交互模式 → Prompt 改进建议
        $promptResults = $this->findPromptImprovements($lookbackHours);
        $results['patterns_found'] += $promptResults;
        $results['by_type']['prompt_improvement'] = $promptResults;

        // 分析2: 知识库缺口 → KB 文章建议
        $kbResults = $this->findKnowledgeGaps($lookbackHours);
        $results['patterns_found'] += $kbResults;
        $results['by_type']['kb_gap'] = $kbResults;

        // 分析3: 参数优化建议
        $tuningResults = $this->findParameterTuning($lookbackHours);
        $results['patterns_found'] += $tuningResults;
        $results['by_type']['parameter_tuning'] = $tuningResults;

        return $results;
    }

    /**
     * 从低质量交互中提取 Prompt 改进建议
     */
    protected function findPromptImprovements(int $hours): int
    {
        $poorInteractions = AiInteractionLog::where('created_at', '>=', now()->subHours($hours))
            ->where(function ($q) {
                $q->where('quality_score', '<', 0.4)
                  ->orWhere('had_hallucination', true)
                  ->orWhere('was_helpful', false);
            })
            ->take(20)
            ->get();

        $count = 0;
        foreach ($poorInteractions as $log) {
            // 跳过太短的 prompt
            if (mb_strlen($log->prompt) < 20) continue;

            try {
                $result = $this->llm->chat([
                    ['role' => 'system', 'content' => implode("\n", [
                        '你是一个 AI 质量分析师。分析以下 AI 交互记录，找出回复质量低的原因，',
                        '并给出具体的改进建议。',
                        '返回 JSON：',
                        '{"root_cause": "根本原因", "suggestion": "具体的改进建议", "target": "改进目标(如 prompt/system_prompt/parameter)", "confidence": 0~1}',
                    ])],
                    ['role' => 'user', 'content' => json_encode([
                        'prompt' => mb_substr($log->prompt, 0, 1000),
                        'response' => mb_substr($log->response ?? '', 0, 500),
                        'had_hallucination' => $log->had_hallucination,
                        'quality_score' => $log->quality_score,
                    ], JSON_UNESCAPED_UNICODE)],
                ], ['temperature' => 0.2, 'max_tokens' => 1000, 'model' => 'deepseek-chat'], 'self_learn_analyze');

                $reply = $result['content'] ?? '';
                if (!preg_match('/\{.*\}/s', $reply, $m)) continue;

                $parsed = json_decode($m[0], true);
                if (!$parsed || empty($parsed['suggestion'])) continue;

                SelfLearningPattern::create([
                    'pattern_type' => 'prompt_improvement',
                    'target' => $log->source_type . '.' . ($parsed['target'] ?? 'prompt'),
                    'current_value' => mb_substr($log->prompt, 0, 500),
                    'suggested_value' => $parsed['suggestion'],
                    'confidence' => min(0.9, $parsed['confidence'] ?? 0.5),
                    'evidence' => "来自交互 #{$log->id}: {$parsed['root_cause']}",
                ]);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('[PatternAnalyzer] prompt分析失败: ' . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * 从用户反复提问中发现知识库缺口
     */
    protected function findKnowledgeGaps(int $hours): int
    {
        // 找到相似的提问模式（同一 session 多次提问同一主题）
        $repeatedQueries = AiInteractionLog::where('created_at', '>=', now()->subHours($hours))
            ->where('source_type', 'ai_friend')
            ->selectRaw('user_id, prompt, count(*) as ask_count')
            ->groupBy('user_id', 'prompt')
            ->having('ask_count', '>=', 2)
            ->take(10)
            ->get();

        $count = 0;
        foreach ($repeatedQueries as $rq) {
            SelfLearningPattern::create([
                'pattern_type' => 'kb_gap',
                'target' => 'knowledge_base.auto_grow',
                'current_value' => null,
                'suggested_value' => "用户反复询问: " . mb_substr($rq->prompt, 0, 200),
                'confidence' => 0.6,
                'evidence' => "用户 #{$rq->user_id} 在 {$hours}小时内重复提问 {$rq->ask_count} 次",
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * 分析参数使用效果
     */
    protected function findParameterTuning(int $hours): int
    {
        $count = 0;

        // 分析温度参数对质量的影响
        $byTemp = AiInteractionLog::where('created_at', '>=', now()->subHours($hours))
            ->whereNotNull('temperature')
            ->whereNotNull('quality_score')
            ->selectRaw('floor(temperature * 10) / 10 as temp_bucket, avg(quality_score) as avg_q, count(*) as cnt')
            ->groupBy('temp_bucket')
            ->having('cnt', '>=', 3)
            ->get();

        if ($byTemp->isNotEmpty()) {
            $best = $byTemp->sortByDesc('avg_q')->first();
            $worst = $byTemp->sortBy('avg_q')->first();

            if ($best && $worst && $best->temp_bucket !== $worst->temp_bucket) {
                SelfLearningPattern::create([
                    'pattern_type' => 'parameter_tuning',
                    'target' => 'ai_friend.temperature',
                    'current_value' => "默认 0.7",
                    'suggested_value' => "推荐温度 {$best->temp_bucket}（平均质量 {$best->avg_q}）",
                    'confidence' => 0.7,
                    'evidence' => "统计 {$hours}小时内 {$best->cnt} 次调用，温度 {$best->temp_bucket} 质量最高",
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * 获取学习统计
     */
    public function getStats(): array
    {
        return [
            'total_patterns' => SelfLearningPattern::count(),
            'pending' => SelfLearningPattern::where('status', 'pending')->count(),
            'applied' => SelfLearningPattern::where('status', 'applied')->count(),
            'by_type' => SelfLearningPattern::selectRaw('pattern_type, count(*) as total')
                ->groupBy('pattern_type')->pluck('total', 'pattern_type')->toArray(),
        ];
    }
}
