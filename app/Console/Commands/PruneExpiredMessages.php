<?php

namespace App\Console\Commands;

use App\Models\ConversationMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneExpiredMessages extends Command
{
    protected $signature = 'messages:prune-expired
        {--batch= : 每批处理数量}
        {--dry-run : 仅预览，不实际删除}
        {--force : 物理删除（默认软删除）}';

    protected $description = '清理过期的聊天消息（软删除或物理删除）';

    public function handle(): int
    {
        if (!config('message-expiry.enabled', true)) {
            $this->warn('消息过期功能已禁用');
            return Command::SUCCESS;
        }

        $batchSize = (int) ($this->option('batch') ?? config('message-expiry.cleanup.batch_size', 500));
        $forceDelete = $this->option('force') ?? config('message-expiry.cleanup.force_delete', false);
        $dryRun = $this->option('dry-run');

        $query = ConversationMessage::expired()->limit($batchSize);
        $count = $query->count();

        if ($count === 0) {
            $this->info('没有过期消息需要清理');
            return Command::SUCCESS;
        }

        $this->line("发现 {$count} 条过期消息");

        if ($dryRun) {
            $this->warn("[DRY RUN] 将删除 {$count} 条消息（未实际执行）");
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $deleted = 0;
        $chunkSize = min(100, $batchSize);

        ConversationMessage::expired()->chunkById($chunkSize, function ($messages) use ($forceDelete, $bar, &$deleted) {
            foreach ($messages as $msg) {
                if ($forceDelete) {
                    $msg->forceDelete();
                } else {
                    $msg->update(['deleted_at' => now()]);
                }
                $deleted++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("清理完成：{$deleted} 条消息已" . ($forceDelete ? '物理删除' : '软删除'));

        Log::info('过期消息清理完成', [
            'count' => $deleted,
            'method' => $forceDelete ? 'force_delete' : 'soft_delete',
        ]);

        return Command::SUCCESS;
    }
}
