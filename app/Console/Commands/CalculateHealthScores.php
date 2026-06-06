<?php

namespace App\Console\Commands;

use App\Services\HealthScoreService;
use Illuminate\Console\Command;

class CalculateHealthScores extends Command
{
    protected $signature = 'hwt:calculate-health-scores';
    protected $description = '批量计算所有活跃客户健康分';

    public function handle(HealthScoreService $service): int
    {
        $this->info('开始批量计算客户健康分...');

        // 假设只有一个租户，多租户环境需要遍历所有租户
        $tenantId = 1;

        $stats = $service->calculateAll($tenantId);

        $this->info("处理完成: {$stats['processed']} 成功, {$stats['failed']} 失败");
        $this->table(
            ['等级', '数量'],
            [
                ['健康(healthy)', $stats['healthy'] ?? 0],
                ['警告(warning)', $stats['warning'] ?? 0],
                ['危险(critical)', $stats['critical'] ?? 0],
            ]
        );

        return Command::SUCCESS;
    }
}
