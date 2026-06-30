<?php

namespace App\Console\Commands;

use App\Services\LlmHealthService;
use Illuminate\Console\Command;

class LlmHealthCheck extends Command
{
    protected $signature = 'llm:health-check
        {--alert-threshold=0 : 失败比例阈值（如 0.5 = 50%），超过触发告警}';

    protected $description = '对所有 LLM Provider 执行健康检查并记录结果';

    public function handle(LlmHealthService $healthService): int
    {
        $this->info('开始 LLM Provider 健康检查...');

        $results = $healthService->checkAll();

        $tableData = [];
        foreach ($results as $slug => $result) {
            $status = $result['is_healthy'] ? '<fg=green>✓ 正常</>' : '<fg=red>✗ 异常</>';
            $latency = $result['latency_ms'] ? "{$result['latency_ms']}ms" : '-';
            $tableData[] = [$slug, strip_tags($status), $latency, $result['error'] ?? '-'];
        }

        $this->table(['Provider', '状态', '延迟', '错误'], $tableData);

        $healthyCount = collect($results)->filter(fn($r) => $r['is_healthy'])->count();
        $totalCount = count($results);

        $this->newLine();
        $this->info("检查完成: {$healthyCount}/{$totalCount} 正常");

        return $healthyCount === $totalCount ? self::SUCCESS : self::FAILURE;
    }
}
