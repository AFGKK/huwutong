<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Services\MaintenanceModeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 维护模式中间件
 *
 * 检测系统是否处于维护模式，如果是则返回 503 + 维护公告。
 * IP 白名单和路径白名单可绕过维护模式。
 */
class MaintenanceMiddleware
{
    public function __construct(
        protected MaintenanceModeService $maintenanceService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->maintenanceService->isActive()) {
            return $next($request);
        }

        $ip = $request->ip() ?? '';
        $path = $request->path();

        if ($this->maintenanceService->canBypass($ip, $path)) {
            return $next($request);
        }

        $data = $this->maintenanceService->getMaintenanceData();
        $config = $this->maintenanceService->getConfig();
        $retryAfter = (string) ($config?->retry_after ?? $data['retry_after'] ?? 60);

        if ($request->expectsJson() || $request->is('api/*')) {
            $response = ApiResponse::error(
                'MAINTENANCE_MODE',
                $data['message'] ?? '系统维护中',
                503,
                [
                    'maintenance' => $data,
                    'retry_after' => (int) $retryAfter,
                ],
            );
            $response->headers->set('Retry-After', $retryAfter);

            return $response;
        }

        return response()
            ->view('public.maintenance', [
                'title' => $data['title'] ?? '系统维护中',
                'message' => $data['message'] ?? '系统正在进行计划内维护，请稍后再试。',
                'scheduledEndAt' => $data['scheduled_end_at'] ?? null,
                'retryAfter' => (int) $retryAfter,
            ], 503)
            ->header('Retry-After', $retryAfter);
    }
}
