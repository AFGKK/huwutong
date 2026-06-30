<?php

namespace App\Console\Commands;

use App\Services\MemoryService;
use Illuminate\Console\Command;

class PruneAiMemories extends Command
{
    protected $signature = 'ai-memory:prune
        {--dry-run : 仅显示将要清理的记录数，不实际删除}';

    protected $description = '清理过期和低置信度的 AI 长期记忆';

    public function handle(MemoryService $memoryService): int
    {
        if ($this->option('dry-run')) {
            $expired = \App\Models\AiMemory::where(function ($q) {
                $q->where('is_active', false)
                    ->orWhere(function ($qq) {
                        $qq->whereNotNull('expires_at')->where('expires_at', '<', now());
                    });
            })->count();

            $lowConfidence = \App\Models\AiMemory::active()
                ->where('confidence', '<', config('ai-memory.pruning.min_confidence_to_keep', 0.1))
                ->count();

            $this->info("模拟清理：过期 {$expired} 条, 低置信度 {$lowConfidence} 条");
            return Command::SUCCESS;
        }

        $result = $memoryService->prune();

        $this->info("已清理：过期 {$result['expired_deleted']} 条, 低置信度 {$result['low_confidence_deleted']} 条");

        return Command::SUCCESS;
    }
}
