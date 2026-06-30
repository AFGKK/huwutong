<?php

namespace App\Services;

use App\Models\SlaBreach;
use App\Models\SlaContract;
use App\Models\SlaMetric;
use App\Models\SlaProbe;
use App\Models\SlaProbeResult;
use App\Models\SlaProbeUptime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlaProbeService
{
    /**
     * 执行单次拨测
     */
    public function probe(SlaProbe $probe): SlaProbeResult
    {
        $startTime = microtime(true);

        try {
            $http = Http::timeout($probe->timeout_seconds)
                ->withOptions(['verify' => false]);

            // 添加自定义请求头
            if ($probe->headers) {
                $http->withHeaders($probe->headers);
            }

            // 发送请求
            $method = strtolower($probe->method ?? 'GET');
            $response = match ($method) {
                'post' => $http->withBody($probe->body ?? '', 'application/json')->post($probe->url),
                'put' => $http->withBody($probe->body ?? '', 'application/json')->put($probe->url),
                'head' => $http->head($probe->url),
                'delete' => $http->delete($probe->url),
                default => $http->get($probe->url),
            };

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $body = $response->body();

            // 判断状态
            $status = 'up';
            $errorMessage = null;

            if ($response->failed()) {
                $status = 'error';
                $errorMessage = "HTTP {$statusCode}";
            } elseif (!$probe->isExpectedStatus($statusCode)) {
                $status = 'error';
                $errorMessage = "预期状态码 {$probe->expected_status}，实际 {$statusCode}";
            } elseif ($probe->expected_body_contains && !str_contains($body, $probe->expected_body_contains)) {
                $status = 'error';
                $errorMessage = "响应体不包含期望内容: {$probe->expected_body_contains}";
            } elseif ($probe->sla_targets && isset($probe->sla_targets['max_response_time'])
                && $responseTimeMs > $probe->sla_targets['max_response_time']) {
                $status = 'slow';
                $errorMessage = "响应超时: {$responseTimeMs}ms > {$probe->sla_targets['max_response_time']}ms";
            }

            // 创建拨测结果
            $result = SlaProbeResult::create([
                'sla_probe_id' => $probe->id,
                'tenant_id' => $probe->tenant_id,
                'status' => $status,
                'response_time_ms' => $responseTimeMs,
                'http_status_code' => $statusCode,
                'error_message' => $errorMessage,
                'response_headers' => $response->headers(),
                'response_size_bytes' => strlen($body),
                'probed_at' => now(),
            ]);

            // 更新拨测状态
            $consecutiveFailures = $status === 'up' ? 0 : ($probe->consecutive_failures + 1);
            $probe->update([
                'last_status' => $status,
                'last_response_time_ms' => $responseTimeMs,
                'last_probed_at' => now(),
                'consecutive_failures' => $consecutiveFailures,
            ]);

            // 连续失败超过阈值 → 触发 SLA Breach
            if ($consecutiveFailures >= 3 && $consecutiveFailures === $probe->consecutive_failures) {
                $this->triggerBreach($probe, $result);
            }

            // 更新 uptime 统计
            $this->updateUptimeStats($probe, $result);

            return $result;

        } catch (\Exception $e) {
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            $result = SlaProbeResult::create([
                'sla_probe_id' => $probe->id,
                'tenant_id' => $probe->tenant_id,
                'status' => 'down',
                'response_time_ms' => $responseTimeMs,
                'error_message' => $e->getMessage(),
                'probed_at' => now(),
            ]);

            $consecutiveFailures = $probe->consecutive_failures + 1;
            $probe->update([
                'last_status' => 'down',
                'last_probed_at' => now(),
                'consecutive_failures' => $consecutiveFailures,
            ]);

            if ($consecutiveFailures >= 3) {
                $this->triggerBreach($probe, $result);
            }

            $this->updateUptimeStats($probe, $result);

            Log::warning('SLA拨测失败', [
                'probe_id' => $probe->id,
                'url' => $probe->url,
                'error' => $e->getMessage(),
            ]);

            return $result;
        }
    }

    /**
     * 批量执行所有应执行的拨测
     */
    public function runAllDue(): int
    {
        $probes = SlaProbe::where('is_active', true)->get();
        $count = 0;

        foreach ($probes as $probe) {
            if ($probe->shouldProbe()) {
                try {
                    $this->probe($probe);
                    $count++;
                } catch (\Exception $e) {
                    Log::error('拨测执行异常', [
                        'probe_id' => $probe->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $count;
    }

    /**
     * 触发 SLA Breach
     */
    protected function triggerBreach(SlaProbe $probe, SlaProbeResult $result): void
    {
        // 查找关联的 SLA Contract（通过 probe name 匹配或根据 tenant 查找 active contract）
        $contract = SlaContract::where('tenant_id', $probe->tenant_id)
            ->where('is_active', true)
            ->first();

        $metric = null;
        if ($contract) {
            $metric = SlaMetric::where('sla_contract_id', $contract->id)
                ->where('metric_key', 'uptime')
                ->first();
        }

        $severity = match (true) {
            $probe->consecutive_failures >= 10 => 'critical',
            $probe->consecutive_failures >= 5 => 'major',
            default => 'minor',
        };

        SlaBreach::create([
            'sla_contract_id' => $contract?->id,
            'sla_metric_id' => $metric?->id,
            'breach_type' => 'probe_failure',
            'severity' => $severity,
            'breachable_type' => SlaProbe::class,
            'breachable_id' => $probe->id,
            'description' => "拨测「{$probe->name}」连续{$probe->consecutive_failures}次失败: {$probe->url}",
            'expected_value' => 100,
            'actual_value' => 0,
            'deviation' => 100,
            'context' => [
                'probe_id' => $probe->id,
                'probe_name' => $probe->name,
                'url' => $probe->url,
                'consecutive_failures' => $probe->consecutive_failures,
                'last_result_id' => $result->id,
                'error_message' => $result->error_message,
            ],
            'status' => 'open',
        ]);
    }

    /**
     * 更新可用性统计
     */
    protected function updateUptimeStats(SlaProbe $probe, SlaProbeResult $result): void
    {
        $today = now()->format('Y-m-d');
        $isSuccess = in_array($result->status, ['up', 'slow']);

        // 日统计
        $daily = SlaProbeUptime::firstOrCreate(
            [
                'sla_probe_id' => $probe->id,
                'tenant_id' => $probe->tenant_id,
                'record_date' => $today,
                'period' => 'daily',
            ],
            [
                'total_checks' => 0,
                'success_checks' => 0,
                'failure_checks' => 0,
                'avg_response_time_ms' => 0,
                'max_response_time_ms' => 0,
                'min_response_time_ms' => 999999,
            ]
        );

        $daily->increment('total_checks');
        if ($isSuccess) {
            $daily->increment('success_checks');
        } else {
            $daily->increment('failure_checks');
        }

        // 更新响应时间
        $rt = $result->response_time_ms ?? 0;
        $newAvg = (($daily->avg_response_time_ms ?? 0) * ($daily->total_checks - 1) + $rt) / $daily->total_checks;
        $daily->update([
            'avg_response_time_ms' => (int) round($newAvg),
            'max_response_time_ms' => max($daily->max_response_time_ms ?? 0, $rt),
            'min_response_time_ms' => $daily->total_checks === 1 ? $rt : min($daily->min_response_time_ms ?? 999999, $rt),
            'uptime_percentage' => round(($daily->success_checks / max($daily->total_checks, 1)) * 100, 2),
        ]);

        // 周/月统计：在读取时计算，不需要每次写入
    }

    // ─── 管理端：CRUD ───

    public function listProbes(int $tenantId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = SlaProbe::where('tenant_id', $tenantId);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('url', 'like', "%{$filters['search']}%");
            });
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'healthy') {
                $query->where('last_status', 'up');
            } elseif ($filters['status'] === 'unhealthy') {
                $query->where(function ($q) {
                    $q->whereIn('last_status', ['down', 'error'])
                      ->orWhere('consecutive_failures', '>=', 1);
                });
            }
        }
        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active'] === 'true');
        }

        return $query->orderByDesc('created_at')->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function getProbe(int $id): SlaProbe
    {
        return SlaProbe::with(['results' => fn($q) => $q->orderByDesc('probed_at')->limit(50)])->findOrFail($id);
    }

    public function createProbe(array $data): SlaProbe
    {
        return SlaProbe::create($data);
    }

    public function updateProbe(SlaProbe $probe, array $data): SlaProbe
    {
        $probe->update($data);
        return $probe->fresh();
    }

    public function deleteProbe(SlaProbe $probe): void
    {
        $probe->results()->delete();
        $probe->uptimeRecords()->delete();
        $probe->delete();
    }

    public function toggleProbe(SlaProbe $probe): SlaProbe
    {
        $probe->update(['is_active' => !$probe->is_active]);
        return $probe->fresh();
    }

    // ─── 拨测结果 & 统计 ───

    public function getResults(int $probeId, array $filters = [], int $perPage = 50): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = SlaProbeResult::where('sla_probe_id', $probeId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from'])) {
            $query->where('probed_at', '>=', Carbon::parse($filters['from']));
        }
        if (!empty($filters['to'])) {
            $query->where('probed_at', '<=', Carbon::parse($filters['to']));
        }

        return $query->orderByDesc('probed_at')->paginate($perPage);
    }

    public function getUptimeStats(int $probeId, string $period = 'daily', int $days = 30): array
    {
        $records = SlaProbeUptime::where('sla_probe_id', $probeId)
            ->where('period', $period)
            ->where('record_date', '>=', now()->subDays($days)->format('Y-m-d'))
            ->orderBy('record_date')
            ->get();

        $aggregate = [
            'total_checks' => $records->sum('total_checks'),
            'success_checks' => $records->sum('success_checks'),
            'failure_checks' => $records->sum('failure_checks'),
            'avg_uptime' => $records->isNotEmpty() ? round($records->avg('uptime_percentage'), 2) : 0,
            'avg_response_time' => $records->isNotEmpty() ? (int) $records->avg('avg_response_time_ms') : 0,
            'max_response_time' => $records->max('max_response_time_ms') ?? 0,
            'min_response_time' => $records->min('min_response_time_ms') ?? 0,
        ];

        return [
            'aggregate' => $aggregate,
            'daily' => $records,
        ];
    }

    public function getDashboard(int $tenantId): array
    {
        $totalProbes = SlaProbe::where('tenant_id', $tenantId)->count();
        $activeProbes = SlaProbe::where('tenant_id', $tenantId)->where('is_active', true)->count();
        $healthyProbes = SlaProbe::where('tenant_id', $tenantId)
            ->where('is_active', true)->where('last_status', 'up')->count();
        $unhealthyProbes = SlaProbe::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('last_status', ['down', 'error', 'slow'])
                  ->orWhere('consecutive_failures', '>=', 1);
            })->count();

        $todayUptime = SlaProbeUptime::whereIn('sla_probe_id',
            SlaProbe::where('tenant_id', $tenantId)->select('id')
        )->where('record_date', now()->format('Y-m-d'))
            ->where('period', 'daily')
            ->selectRaw('SUM(total_checks) as total, SUM(success_checks) as success')
            ->first();

        $overallUptime = $todayUptime && $todayUptime->total > 0
            ? round(($todayUptime->success / $todayUptime->total) * 100, 2)
            : 100;

        return [
            'total_probes' => $totalProbes,
            'active_probes' => $activeProbes,
            'healthy_probes' => $healthyProbes,
            'unhealthy_probes' => $unhealthyProbes,
            'overall_uptime_today' => $overallUptime,
        ];
    }
}
