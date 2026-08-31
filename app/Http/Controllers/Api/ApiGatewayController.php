<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ApiGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiGatewayController extends Controller
{
    public function __construct(
        protected ApiGatewayService $gatewayService
    ) {}

    /**
     * 仪表盘
     * GET /api/v1/admin/api-gateway/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->gatewayService->getDashboard();

        return ApiResponse::success($data, __('app.api.api_gateway.dashboard_fetched'));
    }

    /**
     * 健康检查
     * GET /api/v1/admin/api-gateway/health
     */
    public function health(): JsonResponse
    {
        $data = $this->gatewayService->healthCheck();

        return ApiResponse::success($data, __('app.api.api_gateway.health_check_done'));
    }

    /**
     * 路由列表
     * GET /api/v1/admin/api-gateway/routes
     */
    public function routes(): JsonResponse
    {
        $data = $this->gatewayService->getRoutes();

        return ApiResponse::success($data, __('app.api.api_gateway.routes_fetched'));
    }

    /**
     * 同步路由
     * POST /api/v1/admin/api-gateway/routes/sync
     */
    public function syncRoutes(): JsonResponse
    {
        $result = $this->gatewayService->syncRoutes();

        return ApiResponse::success($result, $result['success'] ? __('app.api.api_gateway.routes_synced') : __('app.api.api_gateway.routes_synced_partial'));
    }

    /**
     * 服务列表
     * GET /api/v1/admin/api-gateway/services
     */
    public function services(): JsonResponse
    {
        $data = $this->gatewayService->getServices();

        return ApiResponse::success($data, __('app.api.api_gateway.services_fetched'));
    }

    /**
     * Upstream 列表
     * GET /api/v1/admin/api-gateway/upstreams
     */
    public function upstreams(): JsonResponse
    {
        $data = $this->gatewayService->getUpstreams();

        return ApiResponse::success($data, __('app.api.api_gateway.upstreams_fetched'));
    }

    /**
     * 插件列表
     * GET /api/v1/admin/api-gateway/plugins
     */
    public function plugins(): JsonResponse
    {
        $data = $this->gatewayService->getPlugins();

        return ApiResponse::success($data, __('app.api.api_gateway.plugins_fetched'));
    }

    /**
     * 配置状态
     * GET /api/v1/admin/api-gateway/config
     */
    public function config(): JsonResponse
    {
        $data = $this->gatewayService->getConfigStatus();

        return ApiResponse::success($data, __('app.api.api_gateway.config_fetched'));
    }

    /**
     * 导出声明式配置
     * GET /api/v1/admin/api-gateway/export
     */
    public function export(): JsonResponse
    {
        $data = $this->gatewayService->exportDeclarativeConfig();

        return ApiResponse::success($data, __('app.api.api_gateway.declarative_exported'));
    }

    /**
     * 引擎信息
     * GET /api/v1/admin/api-gateway/info
     */
    public function info(): JsonResponse
    {
        $data = $this->gatewayService->getEngineInfo();

        return ApiResponse::success($data, __('app.api.api_gateway.engine_fetched'));
    }

    /**
     * 清除缓存
     * POST /api/v1/admin/api-gateway/clear-cache
     */
    public function clearCache(): JsonResponse
    {
        $this->gatewayService->clearCache();

        return ApiResponse::success(null, __('app.api.api_gateway.cache_cleared'));
    }
}
