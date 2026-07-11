<?php

namespace App\Console\Commands;

use App\Models\ConversationMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneDmRetentionMessages extends Command
{
    protected $signature = 'dm:prune-retention
        {--days= : 保留天数，默认读取 config(dm.retention_days)}
        {--batch=500 : 每批处理数量}
        {--dry-run : 仅预览，不实际删除}';

    protected $description = '按私信留存策略软删除超期消息（默认 180 天）';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('dm.retention_days', 180));
        $batch = max(1, (int) $this->option('batch'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $count = ConversationMessage::whereNull('deleted_at')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($count === 0) {
            $this->info("没有超过 {$days} 天的私信消息需要清理");

            return Command::SUCCESS;
        }

        $this->line("发现 {$count} 条超期消息（早于 {$cutoff->toDateTimeString()}）");

        if ($dryRun) {
            $this->warn("[DRY RUN] 将软删除 {$count} 条消息");

            return Command::SUCCESS;
        }

        $deleted = 0;
        ConversationMessage::whereNull('deleted_at')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById($batch, function ($messages) use (&$deleted) {
                $ids = $messages->pluck('id');
                $updated = ConversationMessage::whereIn('id', $ids)
                    ->update(['deleted_at' => now()]);
                $deleted += $updated;
            });

        $this->info("清理完成：{$deleted} 条消息已软删除");

        Log::info('私信留存清理完成', [
            'retention_days' => $days,
            'deleted' => $deleted,
            'cutoff' => $cutoff->toIso8601String(),
        ]);

        return Command::SUCCESS;
    }
}
