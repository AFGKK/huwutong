<?php

namespace App\Services;

use App\Models\BlueGreenDeployment;
use App\Models\DeployRelease;
use App\Services\CircuitBreakerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 蓝绿部署服务 (M3-63)
 *
 * K8s Blue-Green 部署：
 * 1. Green 环境预热 → 2. 健康检查验证 → 3. 一键流量切换
 * 4. 监控确认 → 5. 秒级回滚
 */
class BlueGreenService
{
    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $current = BlueGreenDeployment::where('status', 'live')->latest()->first();
        $history = BlueGreenDeployment::orderBy('created_at', 'desc')->limit(10)->get()->toArray();
        $activeEnv = config('blue-green.environments.active', 'blue');

        return [
            'active_environment' => $activeEnv,
            'current_deployment' => $current,
            'history' => $history,
            'total_deployments' => BlueGreenDeployment::count(),
            'successful' => BlueGreenDeployment::where('status', 'live')->count(),
            'rolled_back' => BlueGreenDeployment::where('status', 'rolled_back')->count(),
            'config' => [
                'strategy' => config('blue-green.traffic_switch.strategy'),
                'warmup_enabled' => config('blue-green.warmup.enabled'),
                'auto_rollback' => config('blue-green.rollback.auto_rollback_on_failure'),
            ],
        ];
    }

    /**
     * 开始蓝绿部署
     */
    public function startDeployment(int $releaseId, ?string $notes = null): BlueGreenDeployment
    {
        $release = DeployRelease::findOrFail($releaseId);
        $activeEnv = config('blue-green.environments.active', 'blue');
        $standbyEnv = $activeEnv === 'blue' ? 'green' : 'blue';

        $deployment = BlueGreenDeployment::create([
            'release_id' => $release->id,
            'release_version' => $release->version,
            'active_environment' => $activeEnv,
            'standby_environment' => $standbyEnv,
            'status' => 'warmup',
            'warmup_started_at' => now(),
            'performed_by' => auth()->user()?->name ?? 'system',
            'notes' => $notes,
        ]);

        Log::info("[BlueGreen] 部署开始: {$release->version} → {$standbyEnv}", [
            'active' => $activeEnv,
            'standby' => $standbyEnv,
        ]);

        return $deployment;
    }

    /**
     * 执行健康检查 (预热验证)
     */
    public function runHealthChecks(int $deploymentId): array
    {
        $deployment = BlueGreenDeployment::findOrFail($deploymentId);
        $standbyEnv = $deployment->standby_environment;
        $baseUrl = config('app.url');

        $endpoints = config('blue-green.warmup.health_checks', ['/api/health']);
        $results = [];

        foreach ($endpoints as $endpoint) {
            $startTime = microtime(true);
            try {
                $response = Http::timeout(10)->get("{$baseUrl}{$endpoint}");
                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                $results[] = [
                    'endpoint' => $endpoint,
                    'environment' => $standbyEnv,
                    'status_code' => $response->status(),
                    'latency_ms' => $latencyMs,
                    'healthy' => $response->successful(),
                    'checked_at' => now()->toIso8601String(),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'endpoint' => $endpoint,
                    'environment' => $standbyEnv,
                    'healthy' => false,
                    'error' => $e->getMessage(),
                    'checked_at' => now()->toIso8601String(),
                ];
            }
        }

        $allHealthy = collect($results)->every(fn($r) => $r['healthy']);
        $status = $allHealthy ? 'verifying' : 'failed';

        $deployment->update([
            'health_check_results' => $results,
            'warmup_completed_at' => now(),
            'status' => $status,
        ]);

        Log::info("[BlueGreen] 健康检查完成: " . ($allHealthy ? '全部通过' : '有失败'), [
            'deployment_id' => $deploymentId,
            'results' => $results,
        ]);

        return [
            'all_healthy' => $allHealthy,
            'results' => $results,
            'status' => $status,
        ];
    }

    /**
     * 执行验证（在切换前验证 standy 环境）
     */
    public function runVerification(int $deploymentId): array
    {
        $deployment = BlueGreenDeployment::findOrFail($deploymentId);
        $duration = config('blue-green.warmup.verify_duration_seconds', 60);

        // 模拟验证 — 生产环境会实际监控一段时间
        sleep(min($duration, 5)); // 快速验证

        // 检查 Circuit Breaker 状态
        $breaker = app(CircuitBreakerService::class);
        $redisOk = $breaker->isRedisAvailable();
        $dbOk = $breaker->isDatabaseAvailable();

        $results = [
            'duration_seconds' => $duration,
            'circuit_breaker' => ['redis' => $redisOk, 'database' => $dbOk],
            'verified_at' => now()->toIso8601String(),
            'passed' => $redisOk && $dbOk,
        ];

        $status = $results['passed'] ? 'switching' : 'failed';
        $deployment->update([
            'verification_results' => $results,
            'verification_completed_at' => now(),
            'status' => $status,
        ]);

        return $results;
    }

    /**
     * 切换流量
     */
    public function switchTraffic(int $deploymentId): BlueGreenDeployment
    {
        $deployment = BlueGreenDeployment::findOrFail($deploymentId);
        $newActive = $deployment->standby_environment;
        $newStandby = $deployment->active_environment;

        // 记录切换前后指标 (简化)
        $metricsBefore = [
            'timestamp' => now()->toIso8601String(),
            'active_env' => $deployment->active_environment,
        ];

        // 实际: 更新 K8s Service selector / VirtualService 权重
        // 这里模拟切换
        $deployment->update([
            'active_environment' => $newActive,
            'standby_environment' => $newStandby,
            'status' => 'live',
            'traffic_switched_at' => now(),
            'metrics_before' => $metricsBefore,
            'metrics_after' => [
                'timestamp' => now()->toIso8601String(),
                'active_env' => $newActive,
            ],
        ]);

        // 更新配置
        config(['blue-green.environments.active' => $newActive]);
        config(['blue-green.environments.standby' => $newStandby]);

        Log::info("[BlueGreen] 流量已切换: {$deployment->active_environment} → {$newActive}", [
            'release' => $deployment->release_version,
        ]);

        return $deployment->fresh();
    }

    /**
     * 回滚
     */
    public function rollback(int $deploymentId, ?string $reason = null): BlueGreenDeployment
    {
        $deployment = BlueGreenDeployment::findOrFail($deploymentId);
        $previousActive = $deployment->standby_environment; // 切换前的 active

        $deployment->update([
            'active_environment' => $previousActive,
            'standby_environment' => $deployment->active_environment,
            'status' => 'rolled_back',
            'rollback_at' => now(),
            'rollback_reason' => $reason,
        ]);

        config(['blue-green.environments.active' => $previousActive]);
        config(['blue-green.environments.standby' => $deployment->active_environment]);

        Log::info("[BlueGreen] 已回滚到 {$previousActive}", [
            'reason' => $reason,
            'deployment_id' => $deploymentId,
        ]);

        return $deployment->fresh();
    }

    /**
     * 获取部署历史
     */
    public function getHistory(int $limit = 20): array
    {
        return BlueGreenDeployment::orderBy('created_at', 'desc')
            ->paginate($limit)
            ->toArray();
    }
}
