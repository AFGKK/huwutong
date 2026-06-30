<?php

namespace App\Console\Commands;

use App\Services\CacheWarmupService;
use Illuminate\Console\Command;

class CacheWarmup extends Command
{
    protected $signature = 'cache:warmup {--source= : 指定数据源名称（可选，不指定则全量预热）}';
    protected $description = '预热 Redis 缓存（预加载热点数据）';

    public function handle(CacheWarmupService $service): int
    {
        $source = $this->option('source');
        $sourceLabel = $source ?: '全部数据源';

        $this->info("开始缓存预热: {$sourceLabel}...");

        $result = $service->warmup($source);

        if (!$result['success']) {
            $this->error("预热失败: {$result['message']}");
            return Command::FAILURE;
        }

        $this->info("✅ 预热完成");
        $this->line("加载记录数: {$result['total_loaded']}");
        $this->line("耗时: {$result['elapsed_seconds']} 秒");

        foreach ($result['results'] as $name => $res) {
            $status = $res['success'] ? '✅' : '❌';
            $detail = $res['success'] ? "{$res['loaded']} 条" : $res['error'];
            $this->line("  {$status} {$name}: {$detail}");
        }

        return Command::SUCCESS;
    }
}
