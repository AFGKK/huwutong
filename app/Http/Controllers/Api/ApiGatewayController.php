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

        return ApiResponse::success($data, 'API 网关仪表盘获取成功');
    }

    /**
     * 健康检查
     * GET /api/v1/admin/api-gateway/health
     */
    public function health(): JsonResponse
    {
        $data = $this->gatewayService->healthCheck();

        return ApiResponse::success($data, '网关健康检查完成');
    }

    /**
     * 路由列表
     * GET /api/v1/admin/api-gateway/routes
     */
    public function routes(): JsonResponse
    {
        $data = $this->gatewayService->getRoutes();

        return ApiResponse::success($data, '路由列表获取成功');
    }

    /**
     * 同步路由
     * POST /api/v1/admin/api-gateway/routes/sync
     */
    public function syncRoutes(): JsonResponse
    {
        $result = $this->gatewayService->syncRoutes();

        return ApiResponse::success($result, $result['success'] ? '路由同步成功' : '路由同步部分失败');
    }

    /**
     * 服务列表
     * GET /api/v1/admin/api-gateway/services
     */
    public function services(): JsonResponse
    {
        $data = $this->gatewayService->getServices();

        return ApiResponse::success($data, '服务列表获取成功');
    }

    /**
     * Upstream 列表
     * GET /api/v1/admin/api-gateway/upstreams
     */
    public function upstreams(): JsonResponse
    {
        $data = $this->gatewayService->getUpstreams();

        return ApiResponse::success($data, 'Upstream 列表获取成功');
    }

    /**
     * 插件列表
     * GET /api/v1/admin/api-gateway/plugins
     */
    public function plugins(): JsonResponse
    {
        $data = $this->gatewayService->getPlugins();

        return ApiResponse::success($data, '插件列表获取成功');
    }

    /**
     * 配置状态
     * GET /api/v1/admin/api-gateway/config
     */
    public function config(): JsonResponse
    {
        $data = $this->gatewayService->getConfigStatus();

        return ApiResponse::success($data, '网关配置获取成功');
    }

    /**
     * 导出声明式配置
     * GET /api/v1/admin/api-gateway/export
     */
    public function export(): JsonResponse
    {
        $data = $this->gatewayService->exportDeclarativeConfig();

        return ApiResponse::success($data, '声明式配置导出成功');
    }

    /**
     * 引擎信息
     * GET /api/v1/admin/api-gateway/info
     */
    public function info(): JsonResponse
    {
        $data = $this->gatewayService->getEngineInfo();

        return ApiResponse::success($data, '引擎信息获取成功');
    }

    /**
     * 清除缓存
     * POST /api/v1/admin/api-gateway/clear-cache
     */
    public function clearCache(): JsonResponse
    {
        $this->gatewayService->clearCache();

        return ApiResponse::success(null, '网关缓存已清除');
    }
}
