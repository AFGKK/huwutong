<?php

namespace App\Console\Commands;

use App\Services\ReportSchedulerService;
use Illuminate\Console\Command;

class ProcessReportSchedules extends Command
{
    protected $signature = 'reports:process-schedules
                            {--dry-run : 仅显示要处理的调度，不实际执行}';
    protected $description = '处理到期的报表调度任务';

    public function handle(ReportSchedulerService $schedulerService): int
    {
        $this->info('检查到期报表调度...');

        $dueSchedules = \App\Models\ReportSchedule::getDueSchedules();

        if (empty($dueSchedules)) {
            $this->info('没有到期的调度任务。');
            return self::SUCCESS;
        }

        $this->info("发现 {$dueSchedules} 个到期调度任务。");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', '报表ID', 'Cron', '下次运行', '格式'],
                array_map(fn($s) => [
                    $s->id,
                    $s->report_id,
                    $s->cron_expression,
                    $s->next_run_at?->format('Y-m-d H:i'),
                    $s->export_format,
                ], $dueSchedules)
            );
            $this->warn('--dry-run 模式，未实际执行。');
            return self::SUCCESS;
        }

        $results = $schedulerService->processDueSchedules();

        $success = 0;
        $failed = 0;
        foreach ($results as $r) {
            if ($r['status'] === 'dispatched') {
                $success++;
            } else {
                $failed++;
                $this->error("调度 #{$r['id']} 失败: {$r['error']}");
            }
        }

        $this->info("处理完成: {$success} 成功, {$failed} 失败");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
