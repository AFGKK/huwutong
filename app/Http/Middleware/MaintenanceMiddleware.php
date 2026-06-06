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
    protected MaintenanceModeService $maintenanceService;

    public function __construct(MaintenanceModeService $maintenanceService)
    {
        $this->maintenanceService = $maintenanceService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 检查是否处于维护模式
        if (! $this->maintenanceService->isActive()) {
            return $next($request);
        }

        $ip = $request->ip();
        $path = $request->path();

        // 白名单绕过
        if ($this->maintenanceService->canBypass($ip, $path)) {
            return $next($request);
        }

        // 返回 503 维护响应
        $data = $this->maintenanceService->getMaintenanceData();
        $config = $this->maintenanceService->getConfig();

        $response = ApiResponse::error(
            'MAINTENANCE_MODE',
            $data['message'] ?? '系统维护中',
            503,
            [
                'maintenance' => $data,
                'retry_after' => $config?->retry_after ?? 60,
            ],
        );

        $response->headers->set('Retry-After', (string) ($config?->retry_after ?? 60));

        return $response;
    }
}
