<?php

namespace App\Services;

use App\Models\AuditAnomaly;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * M2-04 异常检测服务
 *
 * IP 批量激活检测、非常规操作检测、
 * 快速地理位置切换检测、暴力尝试检测。
 */
class AnomalyDetectionService
{
    /**
     * 执行全量异常检测
     */
    public function detectAll(): array
    {
        $results = [];
        $rules = config('anomaly-detection.rules', []);

        foreach ($rules as $key => $rule) {
            if (!($rule['enabled'] ?? false)) {
                continue;
            }
            try {
                $method = 'detect' . str_replace('_', '', ucwords($key, '_'));
                if (method_exists($this, $method)) {
                    $found = $this->$method($rule);
                    $results[$key] = ['checked' => true, 'found' => $found];
                }
            } catch (\Exception $e) {
                Log::error("AnomalyDetection: {$key} failed", ['error' => $e->getMessage()]);
                $results[$key] = ['checked' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * IP 批量激活检测
     */
    protected function detectIpbatchActivation(array $rule): int
    {
        $minutes = $rule['threshold_minutes'] ?? 30;
        $threshold = $rule['threshold_count'] ?? 5;
        $cutoff = now()->subMinutes($minutes);
        $severity = $rule['severity'] ?? 'high';

        $suspects = AuditLog::select('ip_address', DB::raw('count(distinct actionable_id) as license_count'))
            ->where('created_at', '>=', $cutoff)
            ->where('actionable_type', 'App\\Models\\License')
            ->whereNotNull('ip_address')
            ->groupBy('ip_address')
            ->having('license_count', '>=', $threshold)
            ->get();

        $count = 0;
        foreach ($suspects as $suspect) {
            $this->recordAnomaly('ip_batch_activation', $severity, [
                'ip' => $suspect->ip_address,
                'license_count' => $suspect->license_count,
                'window_minutes' => $minutes,
            ], $suspect->ip_address);
            $count++;
        }

        return $count;
    }

    /**
     * 非常规操作检测
     */
    protected function detectUnusualOperation(array $rule): int
    {
        $threshold = $rule['threshold_count'] ?? 10;
        $severity = $rule['severity'] ?? 'medium';
        $quietStart = $rule['quiet_hours_start'] ?? '23:00';
        $quietEnd = $rule['quiet_hours_end'] ?? '06:00';

        $now = now();
        $hour = (int) $now->format('H');
        $startH = (int) explode(':', $quietStart)[0];
        $endH = (int) explode(':', $quietEnd)[0];

        // 是否在静默时段
        $isQuiet = ($hour >= $startH || $hour < $endH);
        if (!$isQuiet) {
            return 0;
        }

        $count = AuditLog::where('created_at', '>=', now()->subHour())
            ->count();

        if ($count >= $threshold) {
            $this->recordAnomaly('unusual_operation', $severity, [
                'operation_count' => $count,
                'window' => '1小时',
                'current_hour' => $hour,
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * 快速地理位置切换检测
     */
    protected function detectRapidGeoSwitch(array $rule): int
    {
        $minutes = $rule['threshold_minutes'] ?? 60;
        $threshold = $rule['threshold_count'] ?? 3;
        $severity = $rule['severity'] ?? 'critical';
        $cutoff = now()->subMinutes($minutes);

        $suspects = AuditLog::select('actionable_id', DB::raw('count(distinct country) as country_count'))
            ->where('created_at', '>=', $cutoff)
            ->where('actionable_type', 'App\\Models\\License')
            ->whereNotNull('country')
            ->groupBy('actionable_id')
            ->having('country_count', '>=', $threshold)
            ->get();

        $count = 0;
        foreach ($suspects as $suspect) {
            $this->recordAnomaly('rapid_geo_switch', $severity, [
                'license_id' => $suspect->actionable_id,
                'country_count' => $suspect->country_count,
                'window_minutes' => $minutes,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * 暴力尝试检测
     */
    protected function detectBruteForceAttempt(array $rule): int
    {
        $minutes = $rule['threshold_minutes'] ?? 15;
        $threshold = $rule['threshold_count'] ?? 10;
        $severity = $rule['severity'] ?? 'critical';
        $cutoff = now()->subMinutes($minutes);

        $suspects = AuditLog::select('ip_address', DB::raw('count(*) as failed_count'))
            ->where('created_at', '>=', $cutoff)
            ->where('action', 'license_activation_failed')
            ->whereNotNull('ip_address')
            ->groupBy('ip_address')
            ->having('failed_count', '>=', $threshold)
            ->get();

        $count = 0;
        foreach ($suspects as $suspect) {
            $this->recordAnomaly('brute_force_attempt', $severity, [
                'ip' => $suspect->ip_address,
                'failed_count' => $suspect->failed_count,
                'window_minutes' => $minutes,
            ], $suspect->ip_address);

            // 自动封禁 IP
            if (config('anomaly-detection.remediation.auto_block_ip', true)) {
                $this->blockIp($suspect->ip_address);
            }
            $count++;
        }

        return $count;
    }

    /**
     * 记录异常
     */
    protected function recordAnomaly(string $type, string $severity, array $details, ?string $ip = null): AuditAnomaly
    {
        $description = $details['description'] ?? ($details['ip'] ?? $type) . ': ' . json_encode($details, JSON_UNESCAPED_UNICODE);

        return AuditAnomaly::create([
            'anomaly_type' => $type,
            'severity' => $severity,
            'metric' => $type,
            'baseline_value' => 0,
            'actual_value' => $details['count'] ?? $details['license_count'] ?? $details['failed_count'] ?? 1,
            'deviation' => 100,
            'description' => $description,
            'context' => array_merge($details, $ip ? ['ip_address' => $ip] : []),
            'status' => 'open',
            'detected_at' => now(),
        ]);
    }

    /**
     * 封禁 IP
     */
    protected function blockIp(string $ip): void
    {
        $duration = config('anomaly-detection.remediation.block_duration_minutes', 120);
        Cache::put('blocked_ip:' . $ip, true, now()->addMinutes($duration));
        Log::warning("AnomalyDetection: IP blocked {$ip} for {$duration}min");
    }

    /**
     * 获取仪表盘统计
     */
    public function getStats(): array
    {
        $total = AuditAnomaly::count();
        $open = AuditAnomaly::where('status', 'open')->count();
        $critical = AuditAnomaly::where('severity', 'critical')->where('status', 'open')->count();
        $resolved = AuditAnomaly::where('status', 'resolved')->count();
        $today = AuditAnomaly::whereDate('detected_at', today())->count();

        $byType = AuditAnomaly::select('anomaly_type', DB::raw('count(*) as count'))
            ->groupBy('anomaly_type')
            ->pluck('count', 'anomaly_type')
            ->toArray();

        return [
            'total' => $total,
            'open' => $open,
            'critical' => $critical,
            'resolved' => $resolved,
            'today' => $today,
            'by_type' => $byType,
        ];
    }

    /**
     * 获取异常列表
     */
    public function getList(array $filters = [], int $perPage = 20)
    {
        $query = AuditAnomaly::orderByDesc('detected_at');

        if (!empty($filters['type'])) {
            $query->where('anomaly_type', $filters['type']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $query->where('description', 'like', "%{$filters['search']}%");
        }

        return $query->paginate($perPage);
    }

    /**
     * 标记异常为已处理
     */
    public function resolve(int $id, ?string $note = null): void
    {
        $data = ['status' => 'resolved', 'acknowledged_at' => now()];
        if ($note) {
            $data['description'] = $note;
        }
        AuditAnomaly::where('id', $id)->update($data);
    }

    /**
     * 获取检测规则配置
     */
    public function getRules(): array
    {
        return config('anomaly-detection.rules', []);
    }
}
