<?php

namespace App\Console\Commands;

use App\Models\CustomReport;
use App\Services\ReportBuilderService;
use Illuminate\Console\Command;

class GenerateScheduledReports extends Command
{
    protected $signature = 'reports:generate-scheduled {--dry-run : Preview without executing}';

    protected $description = '生成所有已配额的定时报表快照';

    public function handle(ReportBuilderService $reportBuilder): int
    {
        $this->info('开始检查定时报表...');

        $reports = CustomReport::where('is_scheduled', true)
            ->whereNotNull('schedule_cron')
            ->get();

        if ($reports->isEmpty()) {
            $this->info('没有找到定时报表');
            return Command::SUCCESS;
        }

        $generated = 0;
        $failed = 0;

        foreach ($reports as $report) {
            if (!$this->shouldRun($report)) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("[DRY-RUN] 将生成报表: {$report->name}");
                continue;
            }

            $this->line("生成报表: {$report->name}...");

            try {
                $reportBuilder->generateSnapshot($report);
                $this->info("  ✓ {$report->name}");
                $generated++;
            } catch (\Exception $e) {
                $this->error("  ✗ {$report->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->table(
            ['统计项', '数值'],
            [
                ['成功', $generated],
                ['失败', $failed],
            ]
        );

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * 判断是否应运行此报表（简化的 cron 匹配）
     */
    protected function shouldRun(CustomReport $report): bool
    {
        $cron = $report->schedule_cron;
        if (empty($cron)) return false;

        // 支持常用的 cron 模式
        $cron = trim($cron);
        $now = now();

        // 每分钟: * * * * *
        if ($cron === '* * * * *') return true;

        $parts = explode(' ', $cron);
        if (count($parts) !== 5) return false;

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        return $this->cronMatch($minute, (int) $now->format('i'))
            && $this->cronMatch($hour, (int) $now->format('G'))
            && $this->cronMatch($dayOfMonth, (int) $now->format('j'))
            && $this->cronMatch($month, (int) $now->format('n'))
            && $this->cronMatch($dayOfWeek, (int) $now->format('N'));
    }

    protected function cronMatch(string $pattern, int $value): bool
    {
        if ($pattern === '*') return true;
        if (str_contains($pattern, ',')) {
            return in_array($value, array_map('intval', explode(',', $pattern)));
        }
        if (str_contains($pattern, '/')) {
            [$start, $step] = explode('/', $pattern);
            $startVal = $start === '*' ? 0 : (int) $start;
            return ($value - $startVal) % (int) $step === 0;
        }
        if (str_contains($pattern, '-')) {
            [$min, $max] = explode('-', $pattern);
            return $value >= (int) $min && $value <= (int) $max;
        }
        return (int) $pattern === $value;
    }
}
