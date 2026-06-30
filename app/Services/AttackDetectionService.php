<?php

namespace App\Services;

use App\Models\AttackEvent;
use App\Models\AttackIpBlock;
use App\Models\LicenseActivation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * M3-36 AI 攻击模式识别
 */
class AttackDetectionService
{
    /**
     * 分析请求并检测攻击
     */
    public function analyzeRequest(string $ip, string $method, string $path, array $context = []): ?AttackEvent
    {
        // 按优先级依次检测
        $detectors = [
            'brute_force' => fn() => $this->detectBruteForce($ip, $method, $path, $context),
            'api_abuse' => fn() => $this->detectApiAbuse($ip, $method, $path, $context),
            'credential_stuffing' => fn() => $this->detectCredentialStuffing($ip, $context),
            'zero_day' => fn() => $this->detectZeroDay($ip, $method, $path, $context),
            'apt_slow' => fn() => $this->detectAptSlow($ip, $context),
        ];

        foreach ($detectors as $type => $detector) {
            if (!config("attack-detection.detectors.{$type}.enabled", true)) continue;

            $result = $detector();
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * 暴力破解检测
     */
    protected function detectBruteForce(string $ip, string $method, string $path, array $context): ?AttackEvent
    {
        $window = config('attack-detection.detectors.brute_force.window_minutes', 5);
        $threshold = config('attack-detection.detectors.brute_force.threshold', 20);

        $cacheKey = "attack:brute:{$ip}";
        $count = Cache::increment($cacheKey);

        if ($count === 1) {
            Cache::expire($cacheKey, $window * 60);
        }

        if ($count >= $threshold) {
            return $this->createEvent('brute_force', 'warning', 0.8, $ip, [
                'method' => $method,
                'path' => $path,
                'request_count' => $count,
                'window_minutes' => $window,
                'description' => "IP {$ip} 在{$window}分钟内请求{$count}次，疑似暴力破解",
            ]);
        }

        return null;
    }

    /**
     * API滥用检测
     */
    protected function detectApiAbuse(string $ip, string $method, string $path, array $context): ?AttackEvent
    {
        $window = config('attack-detection.detectors.api_abuse.window_minutes', 60);
        $threshold = config('attack-detection.detectors.api_abuse.threshold', 100);

        $cacheKey = "attack:api_abuse:{$ip}";
        $count = Cache::increment($cacheKey);
        if ($count === 1) Cache::expire($cacheKey, $window * 60);

        if ($count >= $threshold) {
            return $this->createEvent('api_abuse', 'warning', 0.75, $ip, [
                'method' => $method,
                'path' => $path,
                'request_count' => $count,
                'window_minutes' => $window,
                'description' => "IP {$ip} API调用频率异常({$count}次/{$window}分钟)",
            ]);
        }

        return null;
    }

    /**
     * 分布式撞库检测
     */
    protected function detectCredentialStuffing(string $ip, array $context): ?AttackEvent
    {
        $window = config('attack-detection.detectors.credential_stuffing.window_minutes', 10);
        $threshold = config('attack-detection.detectors.credential_stuffing.threshold', 10);

        $failedKey = "attack:stuffing:{$ip}";
        $failedCount = Cache::increment($failedKey);
        if ($failedCount === 1) Cache::expire($failedKey, $window * 60);

        // 检查是否有大量不同用户名的失败尝试
        $usersKey = "attack:stuffing_users:{$ip}";
        $user = $context['username'] ?? $context['email'] ?? '';
        if ($user) {
            $users = Cache::get($usersKey, []);
            $users[] = $user;
            Cache::put($usersKey, array_unique($users), $window * 60);
            $uniqueUsers = count(array_unique($users));

            if ($uniqueUsers >= $threshold && $failedCount >= $threshold) {
                return $this->createEvent('credential_stuffing', 'critical', 0.9, $ip, [
                    'unique_users_attempted' => $uniqueUsers,
                    'total_attempts' => $failedCount,
                    'description' => "分布式撞库检测: {$uniqueUsers}个不同用户尝试{$failedCount}次",
                ]);
            }
        }

        return null;
    }

    /**
     * 零日利用检测
     */
    protected function detectZeroDay(string $ip, string $method, string $path, array $context): ?AttackEvent
    {
        $anomalyScore = 0;

        // 检测异常路径模式
        $suspiciousPatterns = [
            '/\.\./' => 0.3, '/exec' => 0.4, '/eval' => 0.4,
            'union.*select' => 0.5, '<script' => 0.5,
            '../' => 0.3, 'base64' => 0.3,
            '/admin' => 0.1, '/config' => 0.2,
            '/wp-' => 0.3, '/command' => 0.4,
        ];

        foreach ($suspiciousPatterns as $pattern => $score) {
            if (stripos($path, $pattern) !== false || stripos(http_build_query($context), $pattern) !== false) {
                $anomalyScore = max($anomalyScore, $score);
            }
        }

        // 检测不常见的HTTP方法
        $uncommonMethods = ['PUT', 'DELETE', 'PATCH', 'OPTIONS', 'TRACE'];
        if (in_array(strtoupper($method), $uncommonMethods)) {
            $anomalyScore = max($anomalyScore, 0.2);
        }

        // 检测异常请求头
        $headers = $context['headers'] ?? [];
        if (empty($headers['User-Agent']) || isset($headers['X-Forwarded-Host'])) {
            $anomalyScore = max($anomalyScore, 0.2);
        }

        $threshold = config('attack-detection.detectors.zero_day.threshold_score', 0.7);
        if ($anomalyScore >= $threshold) {
            $severity = $anomalyScore >= 0.8 ? 'critical' : 'warning';
            return $this->createEvent('zero_day', $severity, $anomalyScore, $ip, [
                'method' => $method,
                'path' => $path,
                'anomaly_score' => $anomalyScore,
                'description' => "疑似零日利用: {$method} {$path} (异常分:{$anomalyScore})",
            ]);
        }

        return null;
    }

    /**
     * APT慢速攻击检测
     */
    protected function detectAptSlow(string $ip, array $context): ?AttackEvent
    {
        $window = config('attack-detection.detectors.apt_slow.window_hours', 72);
        $minEvents = config('attack-detection.detectors.apt_slow.min_events', 5);

        $cacheKey = "attack:apt:{$ip}";
        $events = Cache::get($cacheKey, []);

        $events[] = ['time' => now()->timestamp, 'path' => $context['path'] ?? ''];
        $events = array_filter($events, fn($e) => $e['time'] > now()->subHours($window)->timestamp);
        Cache::put($cacheKey, array_values($events), $window * 3600);

        if (count($events) >= $minEvents) {
            // 检查时间间隔是否均匀（慢速特征）
            $timestamps = array_column($events, 'time');
            $intervals = [];
            for ($i = 1; $i < count($timestamps); $i++) {
                $intervals[] = $timestamps[$i] - $timestamps[$i - 1];
            }

            $avgInterval = count($intervals) > 0 ? array_sum($intervals) / count($intervals) : 0;
            // APT慢速攻击特征：间隔较长且均匀
            if ($avgInterval > 600 && $avgInterval < 86400) {
                return $this->createEvent('apt_slow', 'critical', 0.85, $ip, [
                    'event_count' => count($events),
                    'avg_interval_seconds' => round($avgInterval),
                    'window_hours' => $window,
                    'description' => "APT慢速攻击: {$window}h内" . count($events) . "次试探, 平均间隔" . round($avgInterval / 60) . "分钟",
                ]);
            }
        }

        return null;
    }

    /**
     * 创建攻击事件
     */
    protected function createEvent(string $type, string $severity, float $confidence, string $ip, array $context): AttackEvent
    {
        $event = AttackEvent::create([
            'attack_type' => $type,
            'severity' => $severity,
            'confidence' => $confidence,
            'source_ip' => $ip,
            'target' => $context['path'] ?? null,
            'method' => $context['method'] ?? null,
            'path' => $context['path'] ?? null,
            'description' => $context['description'] ?? "检测到{$type}攻击",
            'context' => $context,
            'status' => 'open',
            'detected_at' => now(),
        ]);

        $this->takeAction($event);

        return $event;
    }

    /**
     * 执行自动响应
     */
    protected function takeAction(AttackEvent $event): void
    {
        $actions = [];

        if (config('attack-detection.response.auto_block_ip') && $event->severity === 'critical') {
            $duration = config('attack-detection.response.block_duration_minutes', 60);
            AttackIpBlock::updateOrCreate(
                ['ip' => $event->source_ip],
                [
                    'reason' => $event->description,
                    'attack_type' => $event->attack_type,
                    'confidence' => $event->confidence,
                    'expires_at' => now()->addMinutes($duration),
                    'is_permanent' => false,
                ]
            );
            $actions[] = 'blocked_ip';
            Cache::put("banned:ip:{$event->source_ip}", true, $duration * 60);
        }

        if (config('attack-detection.response.auto_alert_admin')) {
            $actions[] = 'alerted_admin';
        }

        if (!empty($actions)) {
            $event->update(['action_taken' => implode(',', $actions)]);
        }

        Log::warning('Attack detected', [
            'type' => $event->attack_type,
            'severity' => $event->severity,
            'ip' => $event->source_ip,
            'actions' => $actions,
        ]);
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(): array
    {
        $total = AttackEvent::count();
        $open = AttackEvent::where('status', 'open')->count();
        $blocked = AttackEvent::where('status', 'blocked')->count();
        $resolved = AttackEvent::where('status', 'resolved')->count();
        $falsePositive = AttackEvent::where('status', 'false_positive')->count();

        $byType = AttackEvent::selectRaw('attack_type, COUNT(*) as count')
            ->groupBy('attack_type')->pluck('count', 'attack_type')->toArray();

        $bySeverity = AttackEvent::selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')->pluck('count', 'severity')->toArray();

        $activeBlocks = AttackIpBlock::where('expires_at', '>', now())->count();
        $permanentBlocks = AttackIpBlock::where('is_permanent', true)->count();

        $recentEvents = AttackEvent::latest()->limit(20)->get()->toArray();

        return compact('total', 'open', 'blocked', 'resolved', 'falsePositive',
            'byType', 'bySeverity', 'activeBlocks', 'permanentBlocks', 'recentEvents');
    }

    /**
     * 清理过期IP封禁
     */
    public function cleanupExpiredBlocks(): int
    {
        return AttackIpBlock::where('expires_at', '<=', now())
            ->where('is_permanent', false)
            ->delete();
    }
}
