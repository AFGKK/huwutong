<?php

namespace App\Console\Commands;

use App\Services\KbAutoGrowService;
use Illuminate\Console\Command;

class KbAutoGrowCommand extends Command
{
    protected $signature = 'kb:auto-grow
        {--sources=rag_chat,handoff,forum_post,im_chat : 知识来源，逗号分隔}
        {--limit=30 : 每源最多扫描条数}
        {--min-confidence=0.3 : 最低置信度}
        {--dry-run : 仅预览不写入}';

    protected $description = 'AI 知识库自增长：从对话/论坛自动提取知识到 KB';

    public function handle(KbAutoGrowService $service): int
    {
        $this->info('🚀 开始知识库自动增长...');

        $sources = explode(',', $this->option('sources'));
        $limit = (int) $this->option('limit');
        $minConfidence = (float) $this->option('min-confidence');
        $dryRun = $this->option('dry-run');

        $this->line("来源: " . implode(', ', $sources));
        $this->line("每源限制: {$limit}");
        $this->line("最低置信度: {$minConfidence}");

        if ($dryRun) {
            $this->warn('⚠️  --dry-run 模式，不会写入任何数据');
            return Command::SUCCESS;
        }

        $start = microtime(true);
        $results = $service->run([
            'sources' => $sources,
            'limit_per_source' => $limit,
            'min_confidence' => $minConfidence,
        ]);
        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info("📊 提取结果 (耗时: {$elapsed}s)");
        $this->line("总计提取: {$results['total_extracted']} 条");

        foreach ($results['by_source'] as $source => $count) {
            $sourceLabels = [
                'rag_chat' => '💬 AI 客服对话',
                'handoff' => '🎧 人工客服记录',
                'forum_post' => '📝 论坛高赞帖子',
                'im_chat' => '💭 IM 群聊消息',
            ];
            $label = $sourceLabels[$source] ?? $source;
            $icon = $count > 0 ? '✅' : '➖';
            $this->line("  {$icon} {$label}: {$count} 条");
        }

        if ($results['total_extracted'] > 0) {
            $this->newLine();
            $this->info("👉 请运行 php artisan kb:auto-grow-pending 查看待审核草稿");
        }

        return Command::SUCCESS;
    }
}
