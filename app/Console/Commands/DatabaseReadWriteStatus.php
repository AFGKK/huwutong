<?php

namespace App\Console\Commands;

use App\Services\DatabaseReadWriteService;
use Illuminate\Console\Command;

class DatabaseReadWriteStatus extends Command
{
    protected $signature = 'db:read-write-status';
    protected $description = '查看数据库读写分离状态';

    public function handle(DatabaseReadWriteService $service): int
    {
        $status = $service->getStatus();

        $this->info('=== 数据库读写分离状态 ===');
        $this->line("启用: " . ($status['enabled'] ? '✅ 是' : '❌ 否'));
        $this->line("读流量分配: {$status['read_percent']}% → 从库");
        $this->line("从库连接: {$status['replica_connection']}");
        $this->line("从库健康: " . ($status['replica_healthy'] ? '✅ 正常' : '❌ 异常'));
        $this->line("失败计数: {$status['failure_count']}");
        $this->line("");

        if (!empty($status['health'])) {
            $this->info('--- 从库健康详情 ---');
            $this->line("延迟: " . ($status['health']['lag_seconds'] ?? 'N/A') . " 秒");
            $this->line("IO 线程: " . ($status['health']['io_running'] ?? 'N/A'));
            $this->line("SQL 线程: " . ($status['health']['sql_running'] ?? 'N/A'));
            $this->line("最近检查: " . ($status['health']['checked_at'] ?? 'N/A'));
        }

        $this->newLine();
        $this->info('=== 配置 ===');
        foreach ($status['config'] as $key => $val) {
            $this->line("{$key}: {$val}");
        }

        return Command::SUCCESS;
    }
}
