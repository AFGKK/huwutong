<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\VMDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 虚拟环境/模拟器检测管理 (M1.3-14)
 *
 * 检测设备虚拟环境状态的管理后台 API
 */
class VMDetectionController extends Controller
{
    public function __construct(
        protected VMDetectionService $vmDetectionService
    ) {}

    /**
     * 仪表盘
     * GET /api/v1/admin/vm-detection/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->vmDetectionService->getDashboard(), __('app.vm_detection.dashboard_retrieved'));
    }

    /**
     * 已检测设备列表
     * GET /api/v1/admin/vm-detection/devices
     */
    public function devices(Request $request): JsonResponse
    {
        $params = $request->only(['vm_type', 'search', 'per_page', 'page']);
        return ApiResponse::success($this->vmDetectionService->getDetectedDevices($params), __('app.vm_detection.device_list_retrieved'));
    }

    /**
     * 触发检测
     * POST /api/v1/admin/vm-detection/detect/{device}
     */
    public function detect(Device $device): JsonResponse
    {
        $result = $this->vmDetectionService->detect($device);
        return ApiResponse::success($result, __("app.vmdetection.msg_7988938b"));
    }

    /**
     * 获取配置
     * GET /api/v1/admin/vm-detection/config
     */
    public function getConfig(): JsonResponse
    {
        return ApiResponse::success($this->vmDetectionService->getConfig(), __('app.vm_detection.config_retrieved'));
    }

    /**
     * 更新配置
     * PUT /api/v1/admin/vm-detection/config
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'strategy' => 'in:block,reduce_trust,log_only',
            'vm_trust_score' => 'integer|min:0|max:100',
            'detection_threshold' => 'integer|min:1|max:10',
        ]);

        $this->vmDetectionService->updateConfig($validated);
        return ApiResponse::success($this->vmDetectionService->getConfig(), __('app.vm_detection.config_updated'));
    }
}
