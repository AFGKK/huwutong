<?php

namespace App\Console\Commands;

use App\Workflows\WorkflowEngine;
use Illuminate\Console\Command;

/**
 * 处理工作流定时器
 *
 * 处理到期的工作流重试、超时、延迟执行等定时任务。
 * 建议每分钟运行一次（cron: * * * * *）。
 */
class ProcessWorkflowTimers extends Command
{
    protected $signature = 'workflow:process-timers
        {--limit=100 : 一次最多处理的定时器数量}';

    protected $description = '处理到期的工作流定时器（重试/超时/延迟）';

    public function handle(WorkflowEngine $engine): int
    {
        $this->info('Processing workflow timers...');

        $startTime = microtime(true);
        $result = $engine->processTimers();
        $elapsed = (microtime(true) - $startTime) * 1000;

        $this->table(
            ['Type', 'Processed'],
            [
                ['Retries', $result['retries'] ?? 0],
                ['Timeouts', $result['timeouts'] ?? 0],
            ]
        );

        $this->info("Done in {$elapsed}ms");

        return Command::SUCCESS;
    }
}
