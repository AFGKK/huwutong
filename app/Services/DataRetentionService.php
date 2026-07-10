<?php

namespace App\Services;

use App\Models\DataRetentionExecution;
use App\Models\DataRetentionPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 数据留存策略服务 (M1.1-14)
 *
 * 集中管理全系统数据生命周期：
 * - 留存策略 CRUD
 * - 到期数据清理（删除/归档/匿名化）
 * - 执行历史追踪
 * - 存储成本估算
 */
class DataRetentionService
{
    // ─── 策略管理 ────────────────────────────────

    /**
     * 获取所有策略
     */
    public function getPolicies(): array
    {
        return DataRetentionPolicy::ordered()->get()->toArray();
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $cacheKey = 'data_retention:dashboard';
        $ttl = 300;

        return Cache::remember($cacheKey, $ttl, function () {
            $policies = DataRetentionPolicy::all();
            $activeCount = $policies->where('is_active', true)->count();
            $totalCount = $policies->count();

            $recentExecutions = DataRetentionExecution::recent(7)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->toArray();

            $categoryStats = $policies->groupBy('category')
                ->map(fn ($items, $cat) => [
                    'category' => $cat,
                    'total' => $items->count(),
                    'active' => $items->where('is_active', true)->count(),
                ])
                ->values()
                ->toArray();

            $executionStats = DataRetentionExecution::recent(30)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN '.db_is_true('is_dry_run').' THEN 1 ELSE 0 END) as dry_runs,
                    SUM(affected_records) as total_affected
                ')
                ->first();

            return [
                'policies' => [
                    'active' => $activeCount,
                    'total' => $totalCount,
                ],
                'category_stats' => $categoryStats,
                'recent_executions' => $recentExecutions,
                'execution_stats' => $executionStats,
                'estimated_monthly_cleaned' => DataRetentionExecution::recent(30)
                    ->where('status', 'completed')
                    ->sum('affected_records'),
            ];
        });
    }

    /**
     * 清除仪表盘缓存
     */
    public function clearCache(): void
    {
        Cache::forget('data_retention:dashboard');
    }

    /**
     * 同步配置到数据库
     */
    public function syncPoliciesFromConfig(): array
    {
        $config = config('data-retention.policies', []);
        $synced = 0;
        $created = 0;

        foreach ($config as $key => $settings) {
            $existing = DataRetentionPolicy::where('key', $key)->first();

            $data = [
                'name' => $settings['label'] ?? $settings['name'] ?? "策略: {$key}",
                'retention_days' => $settings['retention_days'] ?? 365,
                'action' => $settings['action'] ?? 'delete',
                'archive_enabled' => $settings['archive_enabled'] ?? false,
                'archive_after_days' => $settings['archive_after_days'] ?? null,
                'is_active' => true,
            ];

            // 推断数据表名
            $tableMap = [
                'audit_logs' => 'audit_logs',
                'activity_logs' => 'activity_log',
                'activation_logs' => 'license_activations',
                'webhook_events' => 'webhook_events',
                'webhook_replays' => 'webhook_replays',
                'waf_logs' => 'waf_attack_logs',
                'waf_stats' => 'waf_stats',
                'grpc_logs' => 'grpc_call_logs',
                'notification_logs' => 'notifications',
                'device_heartbeats' => 'device_heartbeats',
                'api_stats' => 'api_usage_stats',
                'failed_jobs' => 'failed_jobs',
                'alert_logs' => 'alerts',
                'slow_query_logs' => 'slow_query_logs',
                'customer_audit_logs' => 'customer_audit_logs',
            ];

            $data['table_name'] = $tableMap[$key] ?? $key;
            $data['category'] = $this->inferCategory($key);

            if ($existing) {
                $existing->update($data);
                $synced++;
            } else {
                $data['key'] = $key;
                DataRetentionPolicy::create($data);
                $created++;
            }
        }

        $this->clearCache();

        return [
            'synced' => $synced,
            'created' => $created,
            'total' => $synced + $created,
            'message' => "同步完成: 新增 {$created} 条, 更新 {$synced} 条",
        ];
    }

    /**
     * 推断分类
     */
    protected function inferCategory(string $key): string
    {
        if (str_contains($key, 'audit')) return 'audit';
        if (str_contains($key, 'waf') || str_contains($key, 'security') || str_contains($key, 'alert')) return 'security';
        if (str_contains($key, 'notification') || str_contains($key, 'email')) return 'notification';
        if (str_contains($key, 'slow') || str_contains($key, 'stats') || str_contains($key, 'api')) return 'performance';
        return 'operation';
    }

    // ─── 策略执行 ────────────────────────────────

    /**
     * 清理到期数据
     */
    public function cleanup(string $policyKey = null, bool $dryRun = false): array
    {
        $query = DataRetentionPolicy::active();

        if ($policyKey) {
            $query->where('key', $policyKey);
        }

        $policies = $query->get();
        $results = [];

        foreach ($policies as $policy) {
            $result = $this->executePolicy($policy, $dryRun);
            $results[] = $result;
        }

        $this->clearCache();

        return [
            'results' => $results,
            'total_policies' => count($policies),
            'total_affected' => collect($results)->sum('affected_records'),
            'dry_run' => $dryRun,
        ];
    }

    /**
     * 执行单条策略
     */
    protected function executePolicy(DataRetentionPolicy $policy, bool $dryRun): array
    {
        $startTime = microtime(true);

        if (!$policy->table_name || !Schema::hasTable($policy->table_name)) {
            return [
                'policy' => $policy->key,
                'status' => 'skipped',
                'message' => "表 {$policy->table_name} 不存在",
                'affected_records' => 0,
            ];
        }

        $table = $policy->table_name;
        $cutoff = now()->subDays($policy->retention_days);
        $batchSize = config('data-retention.cleanup.batch_size', 5000);

        // 创建执行记录
        $execution = DataRetentionExecution::create([
            'policy_key' => $policy->key,
            'table_name' => $table,
            'action' => $policy->action,
            'is_dry_run' => $dryRun,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $totalAffected = 0;
        $batchCount = 0;

        try {
            $baseQuery = DB::table($table)->where('created_at', '<', $cutoff);

            // 如果没有 created_at 列，尝试 updated_at
            if (!$this->hasColumn($table, 'created_at')) {
                $baseQuery = DB::table($table)->where('updated_at', '<', $cutoff);
            }

            $totalRecords = $baseQuery->count();

            if ($totalRecords === 0) {
                $execution->update([
                    'status' => 'completed',
                    'total_records' => 0,
                    'affected_records' => 0,
                    'completed_at' => now(),
                    'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                ]);

                return [
                    'policy' => $policy->key,
                    'status' => 'completed',
                    'affected_records' => 0,
                    'total_records' => 0,
                    'table' => $table,
                ];
            }

            if ($dryRun) {
                $execution->update([
                    'status' => 'completed',
                    'total_records' => $totalRecords,
                    'affected_records' => $totalRecords,
                    'completed_at' => now(),
                    'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                ]);

                return [
                    'policy' => $policy->key,
                    'status' => 'dry_run',
                    'affected_records' => $totalRecords,
                    'total_records' => $totalRecords,
                    'table' => $table,
                ];
            }

            // 分批删除
            do {
                $deleted = DB::table($table)
                    ->where('created_at', '<', $cutoff)
                    ->limit($batchSize)
                    ->delete();

                if (!$this->hasColumn($table, 'created_at')) {
                    $deleted = DB::table($table)
                        ->where('updated_at', '<', $cutoff)
                        ->limit($batchSize)
                        ->delete();
                }

                $totalAffected += $deleted;
                $batchCount++;

                // 批次间隔
                $pause = config('data-retention.cleanup.pause_between_batches', 100);
                if ($pause > 0 && $deleted > 0) {
                    usleep($pause * 1000);
                }

                // 超时检查
                $maxTime = config('data-retention.cleanup.max_execution_time', 300);
                if ((microtime(true) - $startTime) > $maxTime) {
                    Log::warning("数据清理超时: {$policy->key}", [
                        'processed' => $totalAffected,
                        'timeout' => $maxTime,
                    ]);
                    break;
                }
            } while ($deleted > 0);

            $execution->update([
                'status' => 'completed',
                'total_records' => $totalRecords,
                'affected_records' => $totalAffected,
                'batch_count' => $batchCount,
                'completed_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ]);

            Log::info("数据清理完成: {$policy->key}", [
                'table' => $table,
                'affected' => $totalAffected,
                'batches' => $batchCount,
            ]);
        } catch (\Exception $e) {
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'affected_records' => $totalAffected,
                'completed_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ]);

            Log::error("数据清理失败: {$policy->key}", [
                'error' => $e->getMessage(),
                'affected' => $totalAffected,
            ]);

            return [
                'policy' => $policy->key,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'affected_records' => $totalAffected,
            ];
        }

        return [
            'policy' => $policy->key,
            'status' => 'completed',
            'affected_records' => $totalAffected,
            'total_records' => $totalRecords,
            'batches' => $batchCount,
            'table' => $table,
        ];
    }

    /**
     * 检查表是否有指定列
     */
    protected function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Exception) {
            return false;
        }
    }

    // ─── 执行历史 ────────────────────────────────

    /**
     * 获取执行历史
     */
    public function getExecutions(array $filters = []): array
    {
        $query = DataRetentionExecution::orderByDesc('created_at');

        if (!empty($filters['policy_key'])) {
            $query->byPolicy($filters['policy_key']);
        }
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 50), 200);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get()->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 获取存储统计
     */
    public function getStorageStats(): array
    {
        $config = config('data-retention.archive.storage_tiers', []);
        $tiers = [];

        foreach ($config as $key => $tier) {
            $tiers[$key] = [
                'tier' => $key,
                'label' => $tier['label'],
                'cost_per_gb' => $tier['cost_per_gb'],
                'retrieval_time' => $tier['retrieval_time'],
            ];
        }

        return [
            'tiers' => $tiers,
            'estimated_monthly_data_gb' => 0.5, // 占位：后续接入真实统计
            'estimated_monthly_cost' => 0,
        ];
    }
}
