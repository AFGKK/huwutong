<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\IstioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IstioController extends Controller
{
    public function __construct(
        private readonly IstioService $istio
    ) {}

    /**
     * 仪表盘概览
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->istio->getDashboard());
    }

    /**
     * 服务拓扑
     */
    public function serviceTopology(): JsonResponse
    {
        return ApiResponse::success($this->istio->getServiceTopology());
    }

    /**
     * 流量规则
     */
    public function trafficRules(): JsonResponse
    {
        return ApiResponse::success($this->istio->getTrafficRules());
    }

    /**
     * 安全策略
     */
    public function securityPolicies(): JsonResponse
    {
        return ApiResponse::success($this->istio->getSecurityPolicies());
    }

    /**
     * 可观测性配置
     */
    public function observability(): JsonResponse
    {
        return ApiResponse::success($this->istio->getObservabilityConfig());
    }

    /**
     * 金丝雀发布
     */
    public function canaryDeploy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service' => 'required|string|max:100',
            'version' => 'required|string|max:50',
            'weight' => 'nullable|integer|min:1|max:100',
        ]);

        return ApiResponse::success($this->istio->canaryDeploy($validated));
    }

    /**
     * 金丝雀全量发布
     */
    public function promoteCanary(string $service): JsonResponse
    {
        return ApiResponse::success($this->istio->promoteCanary($service));
    }

    /**
     * 回滚金丝雀
     */
    public function rollbackCanary(string $service): JsonResponse
    {
        return ApiResponse::success($this->istio->rollbackCanary($service));
    }

    /**
     * 金丝雀列表
     */
    public function canaryDeployments(): JsonResponse
    {
        return ApiResponse::success($this->istio->getCanaryDeployments());
    }

    /**
     * 部署指南
     */
    public function deploymentGuide(): JsonResponse
    {
        return ApiResponse::success($this->istio->getDeploymentGuide());
    }
}
