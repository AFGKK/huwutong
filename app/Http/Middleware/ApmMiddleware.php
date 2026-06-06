<?php

namespace App\Http\Middleware;

use App\Services\ApmService;
use App\Services\OpenTelemetryService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * APM 性能监控中间件
 *
 * 自动记录每次请求的性能数据，并可选集成 OpenTelemetry 追踪。
 * 使用 DB::listen 统计查询耗时。
 */
class ApmMiddleware
{
    protected ApmService $apmService;
    protected OpenTelemetryService $otelService;

    protected array $dbMetrics = [
        'total_time' => 0,
        'count' => 0,
    ];

    protected ?array $otelContext = null;

    public function __construct(ApmService $apmService, OpenTelemetryService $otelService)
    {
        $this->apmService = $apmService;
        $this->otelService = $otelService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 排除健康检查路径
        $path = $request->path();
        if (str_starts_with($path, 'api/health')) {
            return $next($request);
        }

        // 监听数据库查询
        $this->dbMetrics = ['total_time' => 0, 'count' => 0];
        DB::listen(function ($query) {
            $this->dbMetrics['total_time'] += $query->time;
            $this->dbMetrics['count']++;
        });

        // 开始 OpenTelemetry 追踪跨度
        $this->otelContext = $this->otelService->startRequestSpan($request);

        $response = $next($request);

        // 记录性能数据
        $duration = (microtime(true) - LARAVEL_START) * 1000;

        $metrics = [
            'duration_ms' => $duration,
            'db_duration_ms' => $this->dbMetrics['total_time'],
            'db_queries' => $this->dbMetrics['count'],
        ];

        $this->apmService->record($request, $response, $metrics);

        // 结束 OpenTelemetry 跨度
        $this->otelService->endRequestSpan($this->otelContext, $response, $duration);

        return $response;
    }
}
