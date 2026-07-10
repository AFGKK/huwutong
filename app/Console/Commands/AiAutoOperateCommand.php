<?php

namespace App\Console\Commands;

use App\Services\ContentQualityService;
use Illuminate\Console\Command;

class AiAutoOperateCommand extends Command
{
    protected $signature = 'ai:auto-operate
        {--tasks=messages,channel,forum : 逗号分隔的任务列表}
        {--limit=50 : 每任务最大处理数}
        {--archive-days=90 : 帖子无互动多少天后归档}
        {--dry-run : 仅预览不执行}';

    protected $description = 'AI 自动化运营编排：低质内容折叠/归档/清理';

    public function handle(ContentQualityService $service): int
    {
        $tasks = explode(',', $this->option('tasks'));
        $limit = (int) $this->option('limit');
        $archiveDays = (int) $this->option('archive-days');
        $dryRun = $this->option('dry-run');

        $this->info('🚀 AI 自动化运营编排启动');
        $this->line("任务: " . implode(', ', $tasks));
        $this->line("限制: {$limit}/任务");

        if ($dryRun) {
            $this->warn('⚠️  --dry-run 模式，仅预览');
            return Command::SUCCESS;
        }

        $start = microtime(true);
        $results = $service->runAll($limit, $archiveDays);
        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info("📊 处理结果 (耗时: {$elapsed}s)");

        $labels = [
            'messages' => '💬 IM 消息',
            'channel_messages' => '📢 频道消息',
            'forum_posts' => '📝 广场帖子',
        ];

        foreach ($results as $key => $counts) {
            if ($key === 'total_processed') continue;
            $label = $labels[$key] ?? $key;
            $detail = [];
            foreach ($counts as $action => $count) {
                if ($count > 0) $detail[] = "{$action}:{$count}";
            }
            $detailStr = empty($detail) ? '➖ 无操作'  : '✅ ' . implode(', ', $detail);
            $this->line("  {$label}: {$detailStr}");
        }

        $this->newLine();
        $this->info("总计处理: {$results['total_processed']} 条");

        return Command::SUCCESS;
    }
}
