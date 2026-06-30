<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * AI 自学习引擎
 *
 * 协调完整的学习闭环：
 * 交互日志 → 模式分析 → 自动调优 → 效果验证
 *
 * 系统会自己"变聪明"——通过分析过去的交互来自我改进。
 */
class SelfLearningEngine
{
    protected InteractionLogger $logger;
    protected PatternAnalyzer $analyzer;
    protected AutoTunerService $tuner;

    public function __construct(
        InteractionLogger $logger,
        PatternAnalyzer $analyzer,
        AutoTunerService $tuner,
    ) {
        $this->logger = $logger;
        $this->analyzer = $analyzer;
        $this->tuner = $tuner;
    }

    /**
     * 执行一次完整的学习周期
     *
     * @param array $options {lookback_hours: int, auto_apply: bool, min_confidence: int}
     * @return array{logs: array, patterns: array, tuning: array}
     */
    public function learn(array $options = []): array
    {
        $lookbackHours = $options['lookback_hours'] ?? 24;
        $autoApply = $options['auto_apply'] ?? true;
        $minConfidence = $options['min_confidence'] ?? 70;

        $this->log('info', '🤖 AI 自学习引擎启动', [
            'lookback' => "{$lookbackHours}小时",
            'auto_apply' => $autoApply,
        ]);

        // ─── Step 1: 检查数据基础 ───
        $logs = $this->logger->getStats();

        // ─── Step 2: 模式分析 ───
        $this->log('info', '🔍 开始模式分析...');
        $patterns = $this->analyzer->analyze($lookbackHours);

        // ─── Step 3: 自动调优 ───
        $tuning = ['applied' => 0, 'skipped' => 0];
        if ($autoApply && $patterns['patterns_found'] > 0) {
            $this->log('info', '⚙️ 开始自动调优...');
            $tuning = $this->tuner->autoApply($minConfidence);
        }

        $summary = "发现 {$patterns['patterns_found']} 个模式，应用 {$tuning['applied']} 个优化";
        $this->log('info', "✅ 学习周期完成: {$summary}");

        return [
            'logs' => $logs,
            'patterns' => $patterns,
            'tuning' => $tuning,
            'summary' => $summary,
        ];
    }

    /**
     * 获取系统学习状态
     */
    public function getStatus(): array
    {
        return [
            'logs' => $this->logger->getStats(),
            'patterns' => $this->analyzer->getStats(),
            'tuning' => $this->tuner->getStats(),
            'last_learn_at' => null, // 可以从记录中获取
        ];
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        Log::$level("[SelfLearning] {$message}", $context);
    }
}
