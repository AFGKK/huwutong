<?php

namespace App\Console\Commands;

use App\Services\VectorSearchService;
use Illuminate\Console\Command;

class RebuildSearchIndexCommand extends Command
{
    protected $signature = 'search:rebuild-index
        {--force : 强制重建所有索引}
        {--dry-run : 仅预览不实际执行}';

    protected $description = '重建 AI 搜索增强索引（LLM Embedding）';

    public function handle(VectorSearchService $service): int
    {
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $stats = $service->getStats();
        $this->info("📊 当前索引状态");
        $this->line("  文档总数: {$stats['total_documents']}");
        $this->line("  已索引: {$stats['indexed_documents']} ({$stats['index_coverage']}%)");

        if ($dryRun) {
            $this->warn('⚠️  --dry-run 模式，不会执行重建');
            return Command::SUCCESS;
        }

        if ($this->confirm("将重建 " . ($force ? '全部' : '未索引') . " 文档的 Embedding，是否继续？", true)) {
            $this->info('🔄 开始重建索引...');
            $start = microtime(true);

            $result = $service->rebuildEmbeddings($force);

            $elapsed = round(microtime(true) - $start, 2);
            $this->info("✅ 重建完成 ({$elapsed}s)");
            $this->line("  处理: {$result['updated']}/{$result['total']} 条");
        }

        return Command::SUCCESS;
    }
}
