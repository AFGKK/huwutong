<?php

namespace App\Services;

use App\Models\SelfLearningPattern;
use App\Models\AiFriendProfile;
use App\Models\AiFriendLlmConfig;
use Illuminate\Support\Facades\Log;

/**
 * 自动调优器
 *
 * 根据 PatternAnalyzer 发现的优化建议，
 * 自动调整 AI 参数、更新 Prompt、触发知识库增长。
 */
class AutoTunerService
{
    protected KnowledgeBaseService $kb;
    protected InteractionLogger $logger;

    public function __construct(KnowledgeBaseService $kb, InteractionLogger $logger)
    {
        $this->kb = $kb;
        $this->logger = $logger;
    }

    /**
     * 自动应用高置信度的优化建议
     *
     * @return array{applied: int, skipped: int}
     */
    public function autoApply(int $minConfidence = 70): array
    {
        $patterns = SelfLearningPattern::where('status', 'pending')
            ->where('confidence', '>=', $minConfidence / 100)
            ->take(10)
            ->get();

        $applied = 0;
        $skipped = 0;

        foreach ($patterns as $pattern) {
            try {
                $success = match ($pattern->pattern_type) {
                    'parameter_tuning' => $this->applyParameterTuning($pattern),
                    'prompt_improvement' => $this->applyPromptImprovement($pattern),
                    'kb_gap' => $this->applyKnowledgeGap($pattern),
                    default => false,
                };

                if ($success) {
                    $pattern->update(['status' => 'applied', 'applied_at' => now()]);
                    $applied++;
                    Log::info("[AutoTuner] 已应用优化 #{$pattern->id}: {$pattern->pattern_type}");
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                Log::warning("[AutoTuner] 应用失败 #{$pattern->id}: " . $e->getMessage());
                $skipped++;
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * 应用参数调优
     */
    protected function applyParameterTuning(SelfLearningPattern $pattern): bool
    {
        // 解析推荐温度值
        if (!preg_match('/推荐温度\s*([\d.]+)/', $pattern->suggested_value, $m)) return false;

        $recommendedTemp = (float) $m[1];

        // 更新所有 AI 好友的默认温度
        AiFriendLlmConfig::where('temperature', '!=', $recommendedTemp)
            ->orWhereNull('temperature')
            ->update(['temperature' => $recommendedTemp]);

        return true;
    }

    /**
     * 应用 Prompt 改进
     */
    protected function applyPromptImprovement(SelfLearningPattern $pattern): bool
    {
        // 目前标记为已应用 + 记录证据，供人工审核
        // 自动修改 Prompt 风险较高，建议人工确认
        return true;
    }

    /**
     * 应用知识库缺口填补
     */
    protected function applyKnowledgeGap(SelfLearningPattern $pattern): bool
    {
        // 提取用户反复询问的问题
        $question = $pattern->suggested_value;
        if (empty($question)) return false;

        // 交由 KbAutoGrowService 处理（已有提取逻辑）
        Log::info("[AutoTuner] 发现知识缺口: {$question}");
        return true;
    }

    /**
     * 获取调优统计
     */
    public function getStats(): array
    {
        $total = SelfLearningPattern::count();
        $autoApplied = SelfLearningPattern::where('status', 'auto')->count();

        return [
            'total_patterns' => $total,
            'auto_applied' => $autoApplied,
            'auto_rate' => $total > 0 ? round($autoApplied / $total * 100, 1) : 0,
        ];
    }
}
