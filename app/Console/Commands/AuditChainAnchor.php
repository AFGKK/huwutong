<?php

namespace App\Console\Commands;

use App\Services\MerkleTreeService;
use Illuminate\Console\Command;

class AuditChainAnchor extends Command
{
    protected $signature = 'audit:anchor
        {--force : 即使无新日志也强制锚定}
        {--backfill : 先回填所有未哈希的旧日志}';

    protected $description = '锚定审计日志 Merkle 根哈希，形成防篡改证据链';

    public function handle(MerkleTreeService $merkleTreeService): int
    {
        // 可选的旧日志回填
        if ($this->option('backfill')) {
            $this->info('正在回填未哈希的旧日志...');
            $count = $merkleTreeService->backfillUnhashedLogs();
            $this->info("已回填 {$count} 条日志");
        }

        $stats = $merkleTreeService->getStats();

        if ($stats['unhashed_logs'] > 0 && !$this->option('force')) {
            $this->warn("仍有 {$stats['unhashed_logs']} 条日志未哈希，请先执行 --backfill");
            return self::FAILURE;
        }

        $anchor = $merkleTreeService->anchor();

        $this->info("Merkle 根哈希已锚定:");
        $this->line("  根哈希: {$anchor->root_hash}");
        $prevRootHash = $anchor->prev_root_hash ?? '（首个锚定）';
        $this->line("  前一哈希: {$prevRootHash}");
        $this->line("  覆盖日志: #{$anchor->from_log_id} ~ #{$anchor->to_log_id}");
        $this->line("  日志数量: {$anchor->log_count}");
        $this->line("  锚定时间: {$anchor->anchored_at}");

        return self::SUCCESS;
    }
}
