<?php

namespace App\Console\Commands;

use App\Services\SelfLearningEngine;
use Illuminate\Console\Command;

class AiSelfImproveCommand extends Command
{
    protected $signature = 'ai:self-improve
        {--hours=24 : 分析最近多少小时的交互}
        {--no-apply : 仅分析不自动应用}
        {--min-confidence=70 : 最低置信度百分比}';

    protected $description = 'AI 自学习：分析交互模式→自动优化系统行为';

    public function handle(SelfLearningEngine $engine): int
    {
        $this->info('🧠 AI 自学习引擎启动');
        $this->line("分析窗口: {$this->option('hours')}小时");
        $this->line("自动应用: " . ($this->option('no-apply') ? '❌ 否' : '✅ 是'));

        $start = microtime(true);
        $result = $engine->learn([
            'lookback_hours' => (int) $this->option('hours'),
            'auto_apply' => !$this->option('no-apply'),
            'min_confidence' => (int) $this->option('min-confidence'),
        ]);
        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info("📊 学习报告 (耗时: {$elapsed}s)");

        $this->line("交互总览:");
        $this->line("  总交互: {$result['logs']['total_interactions']}");
        $this->line("  近24h: {$result['logs']['last_24h']}");
        $this->line("  有帮助率: {$result['logs']['helpful_rate']}%");

        $this->line("\n模式分析:");
        foreach ($result['patterns']['by_type'] as $type => $count) {
            $labels = [
                'prompt_improvement' => 'Prompt 改进',
                'kb_gap' => '知识库缺口',
                'parameter_tuning' => '参数调优',
            ];
            $this->line("  {$count} 条 {$labels[$type]}");
        }

        $this->line("\n自动调优:");
        $this->line("  应用: {$result['tuning']['applied']}");
        $this->line("  跳过: {$result['tuning']['skipped']}");

        $this->newLine();
        $this->info("✅ {$result['summary']}");

        return Command::SUCCESS;
    }
}
