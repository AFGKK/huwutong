<?php

namespace App\Console\Commands;

use App\Models\LocalProxyHeartbeat;
use App\Models\LocalProxyActivationLog;
use App\Models\LocalProxyCachedLicense;
use App\Models\LocalProxyNode;
use Illuminate\Console\Command;

class CleanupLocalProxyData extends Command
{
    protected $signature = 'local-proxy:cleanup
        {--dry-run : 仅预览不执行}
        {--days= : 覆盖保留天数}';

    protected $description = '清理本地代理过期数据（心跳日志/激活日志/过期缓存/待激活节点）';

    public function handle(): int
    {
        $dryRun = $this->dryRun;
        $stats = [
            'heartbeats' => 0,
            'activation_logs' => 0,
            'expired_caches' => 0,
            'pending_nodes' => 0,
        ];

        $heartbeatDays = $this->option('days') ?? config('local-proxy.cleanup.heartbeat_retention_days', 30);
        $activationDays = $this->option('days') ?? config('local-proxy.cleanup.activation_log_retention_days', 90);
        $maxPendingHours = config('local-proxy.node.max_pending_hours', 72);
        $maxExpiredCacheDays = config('local-proxy.cache.max_expired_cache_days', 30);
        $batchSize = config('local-proxy.cleanup.cleanup_batch_size', 500);

        // 1. 清理过期心跳
        $heartbeatCutoff = now()->subDays((int) $heartbeatDays);
        $heartbeatQuery = LocalProxyHeartbeat::where('created_at', '<', $heartbeatCutoff);
        $heartbeatCount = (clone $heartbeatQuery)->count();

        if ($heartbeatCount > 0) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] 将清理 {$heartbeatCount} 条过期心跳 (>{$heartbeatDays}天)");
            } else {
                $deleted = 0;
                (clone $heartbeatQuery)->chunk($batchSize, function ($chunk) use (&$deleted) {
                    $deleted += count($chunk);
                    $chunk->each->delete();
                });
                $this->info("  已清理 {$deleted} 条过期心跳");
            }
            $stats['heartbeats'] = $heartbeatCount;
        } else {
            $this->line('  无过期心跳');
        }

        // 2. 清理过期激活日志
        $activationCutoff = now()->subDays((int) $activationDays);
        $activationQuery = LocalProxyActivationLog::where('created_at', '<', $activationCutoff);
        $activationCount = (clone $activationQuery)->count();

        if ($activationCount > 0) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] 将清理 {$activationCount} 条过期激活日志 (>{$activationDays}天)");
            } else {
                $deleted = 0;
                (clone $activationQuery)->chunk($batchSize, function ($chunk) use (&$deleted) {
                    $deleted += count($chunk);
                    $chunk->each->delete();
                });
                $this->info("  已清理 {$deleted} 条过期激活日志");
            }
            $stats['activation_logs'] = $activationCount;
        } else {
            $this->line('  无过期激活日志');
        }

        // 3. 清理过期缓存
        $cacheCutoff = now()->subDays($maxExpiredCacheDays);
        $cacheQuery = LocalProxyCachedLicense::where(function ($q) use ($cacheCutoff) {
            $q->where('expires_at', '<', now())
              ->orWhere('created_at', '<', $cacheCutoff);
        });
        $cacheCount = (clone $cacheQuery)->count();

        if ($cacheCount > 0) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] 将清理 {$cacheCount} 条过期License缓存 (>{$maxExpiredCacheDays}天)");
            } else {
                $deleted = 0;
                (clone $cacheQuery)->chunk($batchSize, function ($chunk) use (&$deleted) {
                    $deleted += count($chunk);
                    $chunk->each->delete();
                });
                $this->info("  已清理 {$deleted} 条过期缓存");
            }
            $stats['expired_caches'] = $cacheCount;
        } else {
            $this->line('  无过期缓存');
        }

        // 4. 清理超时未激活节点
        $pendingCutoff = now()->subHours((int) $maxPendingHours);
        $pendingQuery = LocalProxyNode::where('status', 'pending')
            ->where('created_at', '<', $pendingCutoff);
        $pendingCount = (clone $pendingQuery)->count();

        if ($pendingCount > 0) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] 将清理 {$pendingCount} 个待激活超时节点 (>{$maxPendingHours}小时)");
            } else {
                (clone $pendingQuery)->each(function ($node) {
                    $node->activationLogs()->delete();
                    $node->cachedLicenses()->delete();
                    $node->heartbeats()->delete();
                    $node->config()->delete();
                    $node->delete();
                });
                $this->info("  已清理 {$pendingCount} 个超时待激活节点");
            }
            $stats['pending_nodes'] = $pendingCount;
        } else {
            $this->line('  无超时待激活节点');
        }

        // 汇总
        $this->newLine();
        $this->table(
            ['项目', '数量'],
            [
                ['过期心跳', $stats['heartbeats']],
                ['过期激活日志', $stats['activation_logs']],
                ['过期缓存', $stats['expired_caches']],
                ['超时待激活节点', $stats['pending_nodes']],
            ]
        );

        if ($dryRun) {
            $this->warn('本次为 DRY-RUN，未实际执行任何操作');
        }

        return Command::SUCCESS;
    }
}
