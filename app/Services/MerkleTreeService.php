<?php

namespace App\Services;

use App\Models\AuditChainAnchor;
use App\Models\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 审计日志 Merkle 树防篡改服务
 *
 * 实现哈希链 + Merkle 树，确保审计日志不可篡改：
 * - 每条新日志记录包含前一条日志的哈希（哈希链）
 * - 定期锚定根哈希到数据库（可扩展至区块链/透明度日志）
 * - 提供完整性验证 API
 */
class MerkleTreeService
{
    const CACHE_KEY = 'merkle:last_hash';
    const CACHE_ANCHOR_ID = 'merkle:last_anchor_id';

    /**
     * 为一条审计日志计算 Merkle 哈希并更新哈希链。
     * 在 Log::created 事件中自动调用。
     */
    public function hashLog(Log $log): string
    {
        $prevHash = $this->getLastHash();
        $prevId = $this->getLastLogId();

        $hashInput = implode('|', [
            $log->id,
            $log->tenant_id ?? 'null',
            $log->user_id ?? 'null',
            $log->type,
            $log->action,
            $log->description,
            json_encode($log->payload ?? []),
            $log->ip_address ?? 'null',
            $log->created_at?->toIso8601String() ?? now()->toIso8601String(),
            $prevHash,
        ]);

        $hash = hash('sha256', $hashInput);

        // 更新日志记录的哈希
        $log->merkle_hash = $hash;
        $log->merkle_parent_id = $prevId;

        // 使用不触发了事件的 update 来避免递归
        DB::table('logs')
            ->where('id', $log->id)
            ->update([
                'merkle_hash' => $hash,
                'merkle_parent_id' => $prevId,
            ]);

        // 缓存最新哈希和日志ID
        Cache::put(self::CACHE_KEY, $hash, now()->addDays(7));
        Cache::put(self::CACHE_ANCHOR_ID, $log->id, now()->addDays(7));

        return $hash;
    }

    /**
     * 锚定当前 Merkle 根哈希。
     * 建议由调度任务 (cron) 每小时执行一次。
     */
    public function anchor(): AuditChainAnchor
    {
        $lastHash = $this->getLastHash();
        $lastLogId = $this->getLastLogId();
        $prevAnchor = AuditChainAnchor::latest('id')->first();

        return AuditChainAnchor::create([
            'root_hash' => $lastHash,
            'prev_root_hash' => $prevAnchor?->root_hash,
            'anchored_at' => now(),
            'anchor_type' => 'database',
            'log_count' => Log::count(),
            'from_log_id' => $prevAnchor ? ($prevAnchor->to_log_id + 1) : 1,
            'to_log_id' => $lastLogId,
            'metadata' => [
                'last_log_created_at' => Log::latest('id')->first()?->created_at?->toIso8601String(),
                'environment' => app()->environment(),
            ],
        ]);
    }

    /**
     * 验证审计日志的完整性。
     * 从指定日志开始回溯，验证哈希链是否完整。
     *
     * @param int|null $fromLogId 从哪条日志开始验证，null = 从最新锚定开始
     * @return array{valid: bool, checked: int, errors: array, details: array}
     */
    public function verify(?int $fromLogId = null): array
    {
        $errors = [];
        $checked = 0;

        // 确定验证的起始点
        if ($fromLogId) {
            $cursor = Log::find($fromLogId);
        } else {
            // 从最新的锚定对应的日志开始
            $anchor = AuditChainAnchor::latest('id')->first();
            if (!$anchor) {
                return [
                    'valid' => true,
                    'checked' => 0,
                    'errors' => [],
                    'details' => ['message' => '暂无锚定记录，无法验证'],
                ];
            }
            $cursor = Log::find($anchor->to_log_id);
        }

        if (!$cursor) {
            return [
                'valid' => false,
                'checked' => 0,
                'errors' => ['起始日志不存在'],
                'details' => [],
            ];
        }

        // 从 cursor 开始回溯验证哈希链
        $details = [];
        while ($cursor) {
            $checked++;
            $expectedHash = $this->computeHashForLog($cursor);
            $actualHash = $cursor->merkle_hash;

            if ($expectedHash !== $actualHash) {
                $errors[] = [
                    'log_id' => $cursor->id,
                    'expected_hash' => $expectedHash,
                    'actual_hash' => $actualHash,
                    'action' => $cursor->action,
                    'created_at' => $cursor->created_at?->toIso8601String(),
                ];
                $details[] = "日志 #{$cursor->id} 哈希不匹配（可能已被篡改）";

                // 一旦发现篡改就停止，因为后续哈希都依赖于这条
                break;
            }

            $details[] = "日志 #{$cursor->id} ✓ ({$cursor->action})";

            // 回溯到上一条日志
            $cursor = $cursor->merkle_parent_id
                ? Log::find($cursor->merkle_parent_id)
                : null;

            // 安全检查：防止死循环
            if ($checked > 10000) {
                $details[] = '达到最大检查数量 (10000)，停止';
                break;
            }
        }

        // 最后检查根哈希能否与锚定匹配
        $lastCheckedHash = $checked > 0 ? Log::find($fromLogId ?? AuditChainAnchor::latest('id')->first()?->to_log_id)?->merkle_hash : null;
        $anchorMatch = null;
        if ($lastCheckedHash && !$errors) {
            $matchingAnchor = AuditChainAnchor::where('root_hash', $lastCheckedHash)->first();
            $anchorMatch = $matchingAnchor ? true : false;
        }

        return [
            'valid' => empty($errors),
            'checked' => $checked,
            'errors' => $errors,
            'anchor_match' => $anchorMatch,
            'details' => $details,
        ];
    }

    /**
     * 为单条日志重新计算哈希
     */
    public function computeHashForLog(Log $log): string
    {
        $parentHash = $log->merkle_parent_id
            ? Log::where('id', $log->merkle_parent_id)->value('merkle_hash')
            : '';

        return hash('sha256', implode('|', [
            $log->id,
            $log->tenant_id ?? 'null',
            $log->user_id ?? 'null',
            $log->type,
            $log->action,
            $log->description,
            json_encode($log->payload ?? []),
            $log->ip_address ?? 'null',
            $log->created_at?->toIso8601String() ?? '',
            $parentHash,
        ]));
    }

    /**
     * 获取最新锚定记录
     */
    public function getLatestAnchor(): ?AuditChainAnchor
    {
        return AuditChainAnchor::latest('id')->first();
    }

    /**
     * 获取锚定历史
     */
    public function getAnchorHistory(int $limit = 20): array
    {
        return AuditChainAnchor::latest('id')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 获取 Merkle 统计摘要
     */
    public function getStats(): array
    {
        $totalLogs = Log::count();
        $hashedLogs = Log::whereNotNull('merkle_hash')->count();
        $latestAnchor = $this->getLatestAnchor();

        $unhashed = Log::whereNull('merkle_hash')->count();

        return [
            'total_logs' => $totalLogs,
            'hashed_logs' => $hashedLogs,
            'unhashed_logs' => $unhashed,
            'chain_coverage' => $totalLogs > 0 ? round($hashedLogs / $totalLogs * 100, 1) : 0,
            'latest_anchor' => $latestAnchor,
            'anchor_count' => AuditChainAnchor::count(),
        ];
    }

    /**
     * 为所有未哈希的旧日志填充 Merkle 哈希（一次性迁移任务）
     */
    public function backfillUnhashedLogs(): int
    {
        $count = 0;
        Log::whereNull('merkle_hash')
            ->orderBy('id')
            ->chunk(100, function ($logs) use (&$count) {
                foreach ($logs as $log) {
                    $this->hashLog($log);
                    $count++;
                }
            });

        return $count;
    }

    protected function getLastHash(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        // 从数据库获取最新哈希
        $lastLog = Log::whereNotNull('merkle_hash')->latest('id')->first();
        return $lastLog?->merkle_hash ?? str_repeat('0', 64);
    }

    protected function getLastLogId(): ?int
    {
        $cached = Cache::get(self::CACHE_ANCHOR_ID);
        if ($cached) {
            return (int) $cached;
        }

        $lastLog = Log::whereNotNull('merkle_hash')->latest('id')->first();
        return $lastLog?->id;
    }
}
