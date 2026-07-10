<?php

namespace App\Services;

use App\Models\DataCenter;
use App\Models\FailoverLog;
use App\Models\FailoverRule;
use App\Models\RegionDeployment;
use App\Models\RegionHealthLog;
use App\Models\RegionSyncLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MultiRegionService
{
    // ═══════════ 数据中心管理 ═══════════

    public function listDataCenters(array $filters = []): Collection
    {
        $query = DataCenter::with('latestHealthLog')->orderBy('sort_order');

        if (!empty($filters['region'])) $query->where('region', $filters['region']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (isset($filters['is_active']) && $filters['is_active'] !== 'all') {
            $query->where('is_active', $filters['is_active'] === 'active');
        }

        return $query->get();
    }

    public function createDataCenter(array $data): DataCenter
    {
        return DataCenter::create($data);
    }

    public function updateDataCenter(int $id, array $data): DataCenter
    {
        $dc = DataCenter::findOrFail($id);
        $dc->update($data);
        return $dc->fresh();
    }

    public function deleteDataCenter(int $id): bool
    {
        return DataCenter::findOrFail($id)->delete();
    }

    /**
     * 注册预设数据中心
     */
    public function seedDefaultDataCenters(): array
    {
        $defaults = [
            ['name' => 'AWS 东京', 'code' => 'ap-northeast-1', 'region' => 'asia', 'country_code' => 'JP', 'city' => '东京', 'sort_order' => 1, 'capabilities' => ['compute', 'storage', 'database', 'cache', 'queue']],
            ['name' => 'AWS 新加坡', 'code' => 'ap-southeast-1', 'region' => 'asia', 'country_code' => 'SG', 'city' => '新加坡', 'sort_order' => 2, 'capabilities' => ['compute', 'storage', 'database', 'cache', 'queue']],
            ['name' => 'AWS 法兰克福', 'code' => 'eu-central-1', 'region' => 'europe', 'country_code' => 'DE', 'city' => '法兰克福', 'sort_order' => 3, 'capabilities' => ['compute', 'storage', 'database', 'cache', 'queue']],
            ['name' => 'AWS 弗吉尼亚', 'code' => 'us-east-1', 'region' => 'us', 'country_code' => 'US', 'city' => '弗吉尼亚', 'sort_order' => 4, 'capabilities' => ['compute', 'storage', 'database', 'cache', 'queue']],
            ['name' => 'AWS 俄勒冈', 'code' => 'us-west-2', 'region' => 'us', 'country_code' => 'US', 'city' => '俄勒冈', 'sort_order' => 5, 'capabilities' => ['compute', 'storage']],
            ['name' => 'AWS 悉尼', 'code' => 'ap-southeast-2', 'region' => 'oceania', 'country_code' => 'AU', 'city' => '悉尼', 'sort_order' => 6, 'capabilities' => ['compute', 'storage']],
        ];

        $created = [];
        foreach ($defaults as $dc) {
            $created[] = DataCenter::firstOrCreate(
                ['code' => $dc['code']],
                $dc
            );
        }

        return $created;
    }

    // ═══════════ 区域部署管理 (M3-52) ═══════════

    public function listRegionDeployments(array $filters = []): Collection
    {
        $query = RegionDeployment::orderBy('region_key');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['provider'])) $query->where('provider', $filters['provider']);
        if (isset($filters['is_primary'])) $query->where('is_primary', $filters['is_primary']);

        return $query->get();
    }

    public function showRegionDeployment(int $id): RegionDeployment
    {
        return RegionDeployment::findOrFail($id);
    }

    public function createRegionDeployment(array $data): RegionDeployment
    {
        return RegionDeployment::create($data);
    }

    public function updateRegionDeployment(int $id, array $data): RegionDeployment
    {
        $deployment = RegionDeployment::findOrFail($id);
        $deployment->update($data);
        return $deployment->fresh();
    }

    public function deleteRegionDeployment(int $id): bool
    {
        return RegionDeployment::findOrFail($id)->delete();
    }

    /**
     * 从配置注册三区域部署 (us-east/eu-west/ap-southeast)
     */
    public function seedRegionDeployments(): array
    {
        $config = config('multi-region.regions', []);
        $created = [];

        foreach ($config as $regionKey => $regionConfig) {
            $deployment = RegionDeployment::firstOrCreate(
                ['region_key' => $regionKey],
                [
                    'name' => $regionConfig['name'],
                    'provider' => $regionConfig['provider'] ?? 'aws',
                    'api_url' => $regionConfig['api_url'] ?? '',
                    'status' => 'active',
                    'is_primary' => $regionKey === 'us-east',
                    'weight' => $regionConfig['weight'] ?? 100,
                    'is_healthy' => true,
                    'consecutive_failures' => 0,
                    'config' => $regionConfig,
                ]
            );
            $created[] = $deployment;
        }

        return $created;
    }

    /**
     * 获取最优区域（基于GeoDNS策略）
     */
    public function getOptimalRegion(?string $clientIp = null): array
    {
        $strategy = config('multi-region.routing.strategy', 'geo_dns');
        $deployments = RegionDeployment::where('status', 'active')
            ->where('is_healthy', true)
            ->get();

        if ($deployments->isEmpty()) {
            $fallback = config('multi-region.routing.fallback_region', 'us-east');
            return ['region' => $fallback, 'strategy' => 'fallback'];
        }

        return match ($strategy) {
            'geo_dns' => $this->routeByGeoDNS($deployments, $clientIp),
            'latency_based' => $this->routeByLatency($deployments),
            'weighted_random' => $this->routeByWeightedRandom($deployments),
            default => $this->routeByGeoDNS($deployments, $clientIp),
        };
    }

    protected function routeByGeoDNS(Collection $deployments, ?string $clientIp): array
    {
        // 缓存客户端IP的区域映射（TTL=60s，与应用配置一致）
        $cacheKey = 'geo_region_' . md5($clientIp ?? 'unknown');
        $region = Cache::remember($cacheKey, 60, function () use ($deployments, $clientIp) {
            // 模拟GeoIP探测：当有真实GeoIP数据库时改用 geoip_record_by_name()
            // 此处使用演示逻辑：根据IP段或返回默认区域
            if ($clientIp) {
                // 模拟区域映射 - 生产环境应使用 GeoLite2
                $ipPrefix = substr($clientIp, 0, strrpos($clientIp, '.') ?: 3);
                $hash = crc32($ipPrefix) % 100;

                $cumulative = 0;
                foreach ($deployments as $dep) {
                    $cumulative += $dep->weight;
                    if ($hash < $cumulative) {
                        return $dep->region_key;
                    }
                }
            }

            // 默认返回权重最高的健康区域
            return $deployments->sortByDesc('weight')->first()->region_key;
        });

        $deployment = $deployments->firstWhere('region_key', $region);
        return [
            'region' => $region,
            'api_url' => $deployment?->api_url,
            'strategy' => 'geo_dns',
            'is_primary' => $deployment?->is_primary ?? false,
        ];
    }

    protected function routeByLatency(Collection $deployments): array
    {
        $best = $deployments->sortBy(function ($dep) {
            $latency = $dep->config['latency_base_ms'] ?? 100;
            // 加上少量随机抖动防止雪崩
            return $latency + mt_rand(0, 10);
        })->first();

        return [
            'region' => $best->region_key,
            'api_url' => $best->api_url,
            'strategy' => 'latency_based',
            'is_primary' => $best->is_primary,
        ];
    }

    protected function routeByWeightedRandom(Collection $deployments): array
    {
        $totalWeight = $deployments->sum('weight');
        $rand = mt_rand(1, $totalWeight);
        $cumulative = 0;

        foreach ($deployments as $dep) {
            $cumulative += $dep->weight;
            if ($rand <= $cumulative) {
                return [
                    'region' => $dep->region_key,
                    'api_url' => $dep->api_url,
                    'strategy' => 'weighted_random',
                    'is_primary' => $dep->is_primary,
                ];
            }
        }

        // fallback
        $first = $deployments->first();
        return [
            'region' => $first->region_key,
            'api_url' => $first->api_url,
            'strategy' => 'weighted_random',
            'is_primary' => $first->is_primary,
        ];
    }

    // ═══════════ 跨区域数据同步 (M3-52) ═══════════

    /**
     * 发起跨区域数据同步
     */
    public function startDataSync(string $sourceRegion, string $targetRegion, string $dataType): RegionSyncLog
    {
        $syncLog = RegionSyncLog::create([
            'source_region' => $sourceRegion,
            'target_region' => $targetRegion,
            'data_type' => $dataType,
            'status' => 'running',
            'items_count' => 0,
            'items_synced' => 0,
            'items_failed' => 0,
            'started_at' => now(),
        ]);

        try {
            $sourceDeployment = RegionDeployment::where('region_key', $sourceRegion)->first();
            $targetDeployment = RegionDeployment::where('region_key', $targetRegion)->first();

            if (!$sourceDeployment || !$targetDeployment) {
                throw new \RuntimeException("源区域或目标区域不存在");
            }

            // 构造同步数据
            $items = $this->collectSyncData($dataType, $sourceRegion);
            $syncLog->update(['items_count' => count($items)]);

            $synced = 0;
            $failed = 0;

            foreach ($items as $item) {
                try {
                    $this->sendSyncItem($targetDeployment->api_url, $dataType, $item);
                    $synced++;
                } catch (\Exception $e) {
                    $failed++;
                    Log::warning("跨区域同步失败: {$dataType}@{$sourceRegion}→{$targetRegion}", [
                        'error' => $e->getMessage(),
                        'item' => $item['id'] ?? 'unknown',
                    ]);
                }
            }

            $syncLog->update([
                'status' => $failed > 0 ? 'completed' : 'completed',
                'items_synced' => $synced,
                'items_failed' => $failed,
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $syncLog->fresh();
    }

    /**
     * 收集待同步数据
     */
    protected function collectSyncData(string $dataType, string $sourceRegion): array
    {
        return match ($dataType) {
            'license' => DB::table('licenses')
                ->where('created_at', '>=', now()->subDay())
                ->limit(100)
                ->get()
                ->toArray(),
            'customer' => DB::table('customers')
                ->where('created_at', '>=', now()->subDay())
                ->limit(100)
                ->get()
                ->toArray(),
            'product' => DB::table('products')
                ->limit(100)
                ->get()
                ->toArray(),
            'audit_log' => DB::table('audit_logs')
                ->where('created_at', '>=', now()->subHour())
                ->limit(200)
                ->get()
                ->toArray(),
            default => [],
        };
    }

    /**
     * 发送同步数据到目标区域
     */
    protected function sendSyncItem(string $targetApiUrl, string $dataType, mixed $item): void
    {
        if (!$targetApiUrl) {
            // 无实际API URL时记录日志模拟同步
            Log::info("跨区域同步模拟: {$dataType}@" . ($item->id ?? 'unknown') . " → {$targetApiUrl}");
            return;
        }

        Http::timeout(10)->retry(2, 100)->post("{$targetApiUrl}/api/sync/{$dataType}", [
            'data' => $item,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * 列出同步记录
     */
    public function listSyncLogs(array $filters = [], int $perPage = 20)
    {
        $query = RegionSyncLog::orderByDesc('created_at');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['data_type'])) $query->where('data_type', $filters['data_type']);
        if (!empty($filters['source_region'])) $query->where('source_region', $filters['source_region']);
        if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('created_at', '<=', $filters['date_to']);

        return $query->paginate($perPage);
    }

    /**
     * 检查所有区域的健康状态（新region_health_logs表）
     */
    public function checkAllRegionHealth(): array
    {
        $results = [];
        $deployments = RegionDeployment::where('status', 'active')->get();

        foreach ($deployments as $deployment) {
            $isHealthy = true;
            $responseTime = 0;
            $details = null;

            if ($deployment->api_url) {
                $start = microtime(true);
                try {
                    $response = Http::timeout(5)->get(rtrim($deployment->api_url, '/') . '/api/health');
                    $responseTime = (int)((microtime(true) - $start) * 1000);
                    $isHealthy = $response->successful();
                    if (!$isHealthy) {
                        $details = ['http_status' => $response->status()];
                    }
                } catch (\Exception $e) {
                    $responseTime = (int)((microtime(true) - $start) * 1000);
                    $isHealthy = false;
                    $details = ['error' => $e->getMessage()];
                }
            } else {
                // 模拟延迟
                $responseTime = mt_rand(10, 200);
                $isHealthy = true;
            }

            // 写入新表
            $healthLog = RegionHealthLog::create([
                'region_key' => $deployment->region_key,
                'is_healthy' => $isHealthy,
                'response_time_ms' => $responseTime,
                'checker_region' => 'local',
                'details' => $details ? json_encode($details) : null,
                'checked_at' => now(),
            ]);

            // 更新部署状态
            $deployment->update([
                'is_healthy' => $isHealthy,
                'last_health_check_at' => now(),
                'consecutive_failures' => $isHealthy ? 0 : ($deployment->consecutive_failures + 1),
                'status' => $isHealthy ? 'active' : 'degraded',
            ]);

            $results[] = $healthLog;
        }

        return $results;
    }

    /**
     * 获取区域健康趋势（新表）
     */
    public function getRegionHealthTrend(string $regionKey, int $hours = 24): Collection
    {
        return RegionHealthLog::query()
            ->where('region_key', $regionKey)
            ->where('checked_at', '>=', now()->subHours($hours))
            ->orderBy('checked_at')
            ->get(['region_key', 'is_healthy', 'response_time_ms', 'checked_at']);
    }

    /**
     * 主动-主动健康检查（区域间互检）
     */
    public function crossRegionHealthCheck(): array
    {
        $results = [];
        $deployments = RegionDeployment::where('status', 'active')->get();

        foreach ($deployments as $checker) {
            foreach ($deployments as $target) {
                if ($checker->id === $target->id) continue;

                $isHealthy = true;
                $responseTime = 0;
                $start = microtime(true);

                try {
                    if ($target->api_url) {
                        $response = Http::timeout(5)->get(rtrim($target->api_url, '/') . '/api/health');
                        $responseTime = (int)((microtime(true) - $start) * 1000);
                        $isHealthy = $response->successful();
                    }
                } catch (\Exception $e) {
                    $responseTime = (int)((microtime(true) - $start) * 1000);
                    $isHealthy = false;
                }

                $healthLog = RegionHealthLog::create([
                    'region_key' => $target->region_key,
                    'is_healthy' => $isHealthy,
                    'response_time_ms' => $responseTime,
                    'checker_region' => $checker->region_key,
                    'checked_at' => now(),
                ]);

                $results[] = $healthLog;
            }
        }

        return $results;
    }

    // ═══════════ 健康检查 ═══════════

    /**
     * 执行健康检查（模拟或HTTP探测）
     */
    public function performHealthCheck(DataCenter $dc): RegionHealthLog
    {
        $latency = 0;
        $isHealthy = true;
        $errorMessage = null;
        $metrics = null;

        if ($dc->health_check_url) {
            $start = microtime(true);
            try {
                $response = Http::timeout(10)->get($dc->health_check_url);
                $latency = (microtime(true) - $start) * 1000;
                $isHealthy = $response->successful();
                if (!$isHealthy) {
                    $errorMessage = "HTTP {$response->status()}: " . substr($response->body(), 0, 200);
                }
            } catch (\Exception $e) {
                $latency = (microtime(true) - $start) * 1000;
                $isHealthy = false;
                $errorMessage = $e->getMessage();
            }
        } else {
            // 模拟健康检查（使用随机延迟模拟不同区域的响应时间）
            $latency = fake()->randomFloat(2, 5, 200);
            $isHealthy = true;
        }

        $log = RegionHealthLog::create([
            'data_center_id' => $dc->id,
            'latency_ms' => round($latency, 2),
            'load' => fake()->randomFloat(2, 10, 90),
            'is_healthy' => $isHealthy,
            'check_type' => $dc->health_check_url ? 'http' : 'ping',
            'error_message' => $errorMessage,
            'metrics' => $metrics,
            'checked_at' => now(),
        ]);

        // 更新数据中心状态
        $dc->update([
            'current_latency_ms' => round($latency, 2),
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'last_health_check_at' => now(),
        ]);

        return $log;
    }

    /**
     * 对所有数据中心执行健康检查
     */
    public function healthCheckAll(): array
    {
        $results = [];
        $dcs = DataCenter::active()->get();

        foreach ($dcs as $dc) {
            $results[] = $this->performHealthCheck($dc);
        }

        return $results;
    }

    /**
     * 获取健康趋势
     */
    public function getHealthTrend(int $dataCenterId, int $hours = 24): Collection
    {
        return RegionHealthLog::where('data_center_id', $dataCenterId)
            ->where('checked_at', '>=', now()->subHours($hours))
            ->orderBy('checked_at')
            ->get(['latency_ms', 'is_healthy', 'checked_at']);
    }

    // ═══════════ 故障切换管理 ═══════════

    public function listFailoverRules(int $tenantId, array $filters = []): Collection
    {
        $query = FailoverRule::with(['primaryDc:id,name,code', 'backupDc:id,name,code'])
            ->where('tenant_id', $tenantId);

        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->orderBy('name')->get();
    }

    public function createFailoverRule(int $tenantId, array $data): FailoverRule
    {
        $data['tenant_id'] = $tenantId;
        if (empty($data['status'])) $data['status'] = 'active';
        return FailoverRule::create($data);
    }

    public function updateFailoverRule(int $id, array $data): FailoverRule
    {
        $rule = FailoverRule::findOrFail($id);
        $rule->update($data);
        return $rule->fresh();
    }

    public function deleteFailoverRule(int $id): bool
    {
        return FailoverRule::findOrFail($id)->delete();
    }

    /**
     * 执行故障切换
     */
    public function executeFailover(FailoverRule $rule, string $reason, bool $automatic = false): FailoverLog
    {
        $primaryDc = $rule->primaryDc;
        $backupDc = $rule->backupDc;

        $log = FailoverLog::create([
            'failover_rule_id' => $rule->id,
            'tenant_id' => $rule->tenant_id,
            'action' => $automatic ? 'failover' : 'manual_failover',
            'from_dc' => $primaryDc->code,
            'to_dc' => $backupDc->code,
            'trigger_reason' => $reason,
            'is_automatic' => $automatic,
            'metrics_snapshot' => [
                'primary_latency' => $primaryDc->current_latency_ms,
                'primary_status' => $primaryDc->status,
                'backup_latency' => $backupDc->current_latency_ms,
                'backup_status' => $backupDc->status,
            ],
        ]);

        $rule->update([
            'status' => 'failover',
            'last_failover_at' => now(),
        ]);

        return $log;
    }

    /**
     * 执行恢复（回切）
     */
    public function executeRestore(FailoverRule $rule, string $reason): FailoverLog
    {
        $primaryDc = $rule->primaryDc;
        $backupDc = $rule->backupDc;

        $log = FailoverLog::create([
            'failover_rule_id' => $rule->id,
            'tenant_id' => $rule->tenant_id,
            'action' => 'restore',
            'from_dc' => $backupDc->code,
            'to_dc' => $primaryDc->code,
            'trigger_reason' => $reason,
            'is_automatic' => false,
            'metrics_snapshot' => [
                'primary_latency' => $primaryDc->current_latency_ms,
                'primary_status' => $primaryDc->status,
                'backup_latency' => $backupDc->current_latency_ms,
                'backup_status' => $backupDc->status,
            ],
        ]);

        $rule->update([
            'status' => 'active',
            'last_failover_at' => now(),
        ]);

        return $log;
    }

    /**
     * 自动检测并执行故障切换
     */
    public function autoFailoverCheck(int $tenantId): array
    {
        $results = [];
        $rules = FailoverRule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('auto_failover', true)
            ->whereIn('status', ['active', 'restoring'])
            ->with(['primaryDc', 'backupDc'])
            ->get();

        foreach ($rules as $rule) {
            $primaryDc = $rule->primaryDc;

            if (!$primaryDc) continue;

            // 检查主数据中心是否宕机
            if ($primaryDc->status === 'down') {
                // 检查最近的失败次数
                $recentFailures = RegionHealthLog::where('data_center_id', $primaryDc->id)
                    ->where('checked_at', '>=', now()->subMinutes(10))
                    ->where('is_healthy', false)
                    ->count();

                if ($recentFailures >= $rule->failure_count_threshold) {
                    $this->executeFailover($rule, "主数据中心 {$primaryDc->name} 宕机，连续{$recentFailures}次健康检查失败", true);
                    $results[] = [
                        'rule' => $rule->name,
                        'action' => 'failover',
                        'to' => $rule->backupDc?->name,
                    ];
                }
            }

            // 延迟触发
            if ($rule->trigger_type === 'latency' && $rule->trigger_threshold_ms && $primaryDc->status === 'healthy') {
                if ($primaryDc->current_latency_ms && $primaryDc->current_latency_ms > $rule->trigger_threshold_ms) {
                    $recentHighLatency = RegionHealthLog::where('data_center_id', $primaryDc->id)
                        ->where('checked_at', '>=', now()->subMinutes(5))
                        ->where('latency_ms', '>', $rule->trigger_threshold_ms)
                        ->count();

                    if ($recentHighLatency >= $rule->failure_count_threshold) {
                        $this->executeFailover($rule, "主数据中心 {$primaryDc->name} 延迟 {$primaryDc->current_latency_ms}ms 超过阈值 {$rule->trigger_threshold_ms}ms", true);
                        $results[] = [
                            'rule' => $rule->name,
                            'action' => 'failover',
                            'to' => $rule->backupDc?->name,
                        ];
                    }
                }
            }
        }

        return $results;
    }

    // ═══════════ 故障切换日志 ═══════════

    public function listFailoverLogs(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = FailoverLog::where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (!empty($filters['action'])) $query->where('action', $filters['action']);
        if (!empty($filters['rule_id'])) $query->where('failover_rule_id', $filters['rule_id']);
        if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('created_at', '<=', $filters['date_to']);

        return $query->paginate($perPage);
    }

    // ═══════════ 仪表盘 ═══════════

    public function getDashboard(int $tenantId): array
    {
        $dcs = DataCenter::with('latestHealthLog')->orderBy('sort_order')->get();

        $rules = FailoverRule::with(['primaryDc:id,name,code', 'backupDc:id,name,code'])
            ->where('tenant_id', $tenantId)
            ->get();

        $recentLogs = FailoverLog::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentHealthChecks = RegionHealthLog::whereIn('data_center_id', $dcs->pluck('id'))
            ->where('checked_at', '>=', now()->subHours(24))
            ->selectRaw('data_center_id, COUNT(*) as checks, AVG(latency_ms) as avg_latency, SUM(CASE WHEN '.db_is_false('is_healthy').' THEN 1 ELSE 0 END) as failures')
            ->groupBy('data_center_id')
            ->get()
            ->keyBy('data_center_id');

        // M3-52 区域部署状态
        $regionDeployments = RegionDeployment::orderBy('region_key')->get();
        $regionHealthSummaries = RegionHealthLog::query()
            ->whereIn('region_key', $regionDeployments->pluck('region_key'))
            ->where('checked_at', '>=', now()->subHours(24))
            ->selectRaw('region_key, COUNT(*) as checks, AVG(response_time_ms) as avg_response_time, SUM(CASE WHEN '.db_is_false('is_healthy').' THEN 1 ELSE 0 END) as failures')
            ->groupBy('region_key')
            ->get()
            ->keyBy('region_key');

        return [
            'data_centers' => $dcs,
            'failover_rules' => $rules,
            'recent_logs' => $recentLogs,
            'health_summary' => $recentHealthChecks,
            'region_deployments' => $regionDeployments,
            'region_health_summaries' => $regionHealthSummaries,
            'stats' => [
                'total_dcs' => $dcs->count(),
                'healthy_dcs' => $dcs->whereIn('status', ['healthy', 'degraded'])->count(),
                'down_dcs' => $dcs->where('status', 'down')->count(),
                'total_rules' => $rules->count(),
                'active_rules' => $rules->where('status', 'active')->count(),
                'failover_rules' => $rules->where('status', 'failover')->count(),
                'avg_latency' => round($dcs->whereNotNull('current_latency_ms')->avg('current_latency_ms') ?? 0, 2),
                'total_region_deployments' => $regionDeployments->count(),
                'healthy_regions' => $regionDeployments->where('is_healthy', true)->count(),
                'unhealthy_regions' => $regionDeployments->where('is_healthy', false)->count(),
            ],
        ];
    }
}
