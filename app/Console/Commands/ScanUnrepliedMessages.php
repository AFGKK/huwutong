<?php

namespace App\Console\Commands;

use App\Services\AiProactiveInsightService;
use Illuminate\Console\Command;

class ScanUnrepliedMessages extends Command
{
    protected $signature = 'ai:scan-unreplied {--limit=20 : 最大生成洞察数}';
    protected $description = '扫描未回复对话，生成主动洞察推送';

    public function handle(AiProactiveInsightService $service): int
    {
        $this->info('🔍 开始扫描未回复对话...');

        $generated = $service->scanAll();

        $this->info("✅ 扫描完成，生成了 {$generated} 条主动洞察");

        return Command::SUCCESS;
    }
}
