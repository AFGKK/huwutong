<?php

namespace App\Console\Commands;

use App\Services\RedisHaService;
use Illuminate\Console\Command;

class RedisHaStatusCommand extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'redis-ha:status
                           {--watch : 持续监控模式}
                           {--interval=5 : 监控刷新间隔（秒）}
                           {--json : JSON 格式输出}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '检查 Redis 高可用运行状态';

    /**
     * 执行命令
     */
    public function handle(RedisHaService $service): int
    {
        $mode = $service->getMode();
        $this->components->twoColumnDetail('运行模式', $mode);
        $this->newLine();

        if ($this->option('watch')) {
            $this->watchMode($service);
            return self::SUCCESS;
        }

        // 健康检查
        $this->components->section('健康检查');
        $health = $service->healthCheck();

        if ($this->option('json')) {
            $this->output->writeln(json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->table(
            ['指标', '数值'],
            [
                ['Ping', $health['ping'] ? '<fg=green>✓ 正常</>' : '<fg=red>✗ 失败</>'],
                ['延迟', "{$health['latency_ms']} ms"],
                ['角色', $health['role'] ?? 'unknown'],
                ['从库数量', $health['connected_slaves']],
                ['运行时间', gmdate('H:i:s', $health['uptime_in_seconds'] ?? 0)],
                ['命中率', "{$health['keyspace_hit_ratio']}%"],
                ['内存', $health['memory_usage']['used'] ?? 'N/A'],
                ['内存峰值', $health['memory_usage']['peak'] ?? 'N/A'],
            ]
        );

        // 综合状态
        $this->newLine();
        $this->components->section('综合状态');
        $status = $service->checkStatus();

        $statusIcon = $status['overall_status'] === 'ok' ? '<fg=green>✓</>' : ($status['overall_status'] === 'warning' ? '<fg=yellow>⚠</>' : '<fg=red>✗</>');
        $this->components->twoColumnDetail('健康状态', "{$statusIcon} {$status['overall_status']}");
        $this->components->twoColumnDetail('故障转移', $status['failover_available'] ? '<fg=green>可用</>' : '<fg=red>不可用</>');

        if (count($status['issues']) > 0) {
            $this->newLine();
            $this->components->section('告警信息');
            foreach ($status['issues'] as $issue) {
                $icon = $issue['severity'] === 'critical' ? '<fg=red>✗</>' : '<fg=yellow>⚠</>';
                $this->warn(" {$icon} [{$issue['component']}] {$issue['message']}");
            }
        }

        // Sentinel 状态
        if ($mode === 'sentinel') {
            $this->newLine();
            $this->components->section('Sentinel 哨兵状态');
            $sentinel = $service->sentinelStatus();

            if ($sentinel['master'] ?? false) {
                $this->components->twoColumnDetail('主库', "{$sentinel['master']['host']}:{$sentinel['master']['port']}");
            }
            $this->components->twoColumnDetail('从库数量', count($sentinel['slaves'] ?? []));
            $this->components->twoColumnDetail('哨兵数量', count($sentinel['sentinels'] ?? []));
        }

        return self::SUCCESS;
    }

    /**
     * 持续监控模式
     */
    protected function watchMode(RedisHaService $service): void
    {
        $interval = (int) $this->option('interval');

        while (true) {
            $this->output->write("\033[2J\033[H"); // 清屏
            $this->output->writeln(sprintf(
                '<fg=cyan>Redis HA 监控 (刷新间隔: %ds) — %s</>',
                $interval,
                now()->toDateTimeString()
            ));
            $this->output->writeln(str_repeat('─', 50));

            $health = $service->healthCheck();
            $pingIcon = $health['ping'] ? '✓' : '✗';
            $pingColor = $health['ping'] ? 'green' : 'red';

            $this->output->writeln(sprintf(
                ' Ping: <fg=%s>%s</> | 延迟: <fg=%s>%.2fms</> | 角色: %s | 从库: %d',
                $pingColor,
                $pingIcon,
                $health['latency_ms'] > 50 ? 'yellow' : 'green',
                $health['latency_ms'],
                $health['role'],
                $health['connected_slaves']
            ));

            $mem = $health['memory_usage'];
            if ($mem && $mem['percent'] > 0) {
                $memColor = $mem['percent'] > 80 ? 'red' : 'green';
                $this->output->writeln(sprintf(
                    ' 内存: <fg=%s>%s / %s (%01.1f%%)</>',
                    $memColor,
                    $mem['used'],
                    $mem['max'],
                    $mem['percent']
                ));
            }

            $this->output->writeln(sprintf(' 命中率: %.2f%%', $health['keyspace_hit_ratio']));

            sleep($interval);
        }
    }
}
