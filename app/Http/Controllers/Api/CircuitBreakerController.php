<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CircuitBreakerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreakerController extends Controller
{
    public function __construct(
        protected CircuitBreakerService $circuitBreaker,
    ) {}

    /**
     * 获取所有服务的熔断状态概览
     */
    public function index(): JsonResponse
    {
        $states = $this->circuitBreaker->getAllStates();
        $services = array_keys($states);

        $detailed = [];
        foreach ($services as $svc) {
            $state = $states[$svc]['state'];
            $prefix = 'circuit_breaker:';

            $detailed[] = [
                'service' => $svc,
                'label' => $this->serviceLabel($svc),
                'state' => $state,
                'failures' => $states[$svc]['failures'],
                'available' => $states[$svc]['available'],
                'threshold' => $this->getThreshold($svc),
                'state_changed_at' => Cache::get($prefix . "state_changed:{$svc}"),
                'half_open_count' => (int) Cache::get($prefix . "half_open_count:{$svc}", 0),
            ];
        }

        // 聚合统计
        $openCount = count(array_filter($detailed, fn($d) => $d['state'] === 'open'));
        $halfOpenCount = count(array_filter($detailed, fn($d) => $d['state'] === 'half_open'));
        $closedCount = count(array_filter($detailed, fn($d) => $d['state'] === 'closed'));

        return ApiResponse::success([
            'services' => $detailed,
            'summary' => [
                'total' => count($detailed),
                'closed' => $closedCount,
                'open' => $openCount,
                'half_open' => $halfOpenCount,
                'all_healthy' => $openCount === 0 && $halfOpenCount === 0,
            ],
        ]);
    }

    /**
     * 重置指定服务的熔断状态
     */
    public function reset(Request $request): JsonResponse
    {
        $service = $request->input('service');

        if ($service) {
            $this->circuitBreaker->resetService($service);
            Log::info("手动重置熔断服务: {$service}");
        } else {
            $count = $this->circuitBreaker->resetAll();
            Log::info("手动重置所有熔断服务");
        }

        return ApiResponse::success(null, __('app.api.circuit_breaker.reset_done'));
    }

    /**
     * 获取熔断事件日志
     */
    public function logs(): JsonResponse
    {
        // 从日志文件读取最近熔断相关日志（简化版，真实场景可使用 ELK/Grafana）
        $logFile = storage_path('logs/laravel.log');
        $events = [];

        if (file_exists($logFile)) {
            $lines = [];
            $handle = fopen($logFile, 'r');
            if ($handle) {
                $position = max(0, filesize($logFile) - 65536); // 读取最后 64KB
                fseek($handle, $position);
                fgets($handle); // 跳过可能不完整的首行
                while (($line = fgets($handle)) !== false) {
                    if (str_contains($line, '已熔断') || str_contains($line, '已恢复') || str_contains($line, '半开探测失败') || str_contains($line, '尝试半开恢复') || str_contains($line, '手动重置')) {
                        $lines[] = $line;
                    }
                }
                fclose($handle);
            }

            // 取最新的 50 条
            $lines = array_slice(array_reverse($lines), 0, 50);
            foreach ($lines as $line) {
                preg_match('/^\[(.*?)\]/', $line, $m);
                $timestamp = $m[1] ?? null;
                $message = trim(substr($line, strpos($line, ']') + 1));

                $events[] = [
                    'timestamp' => $timestamp,
                    'message' => $message,
                    'level' => str_contains($line, '.error') ? 'error'
                        : (str_contains($line, '.warning') ? 'warning' : 'info'),
                ];
            }
        }

        return ApiResponse::success(array_slice($events, 0, 50));
    }

    protected function serviceLabel(string $svc): string
    {
        return match ($svc) {
            'redis' => 'Redis 缓存',
            'db', 'database' => '数据库',
            'license' => 'License 服务',
            'webhook' => 'Webhook 服务',
            'sso' => 'SSO 单点登录',
            'feature_flag' => 'Feature Flag',
            default => $svc,
        };
    }

    protected function getThreshold(string $service): int
    {
        return match ($service) {
            'redis' => 3,
            'db', 'database' => 5,
            default => 5,
        };
    }
}
