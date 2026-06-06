<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceConfig;
use App\Services\MaintenanceModeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MaintenanceModeController extends Controller
{
    public function __construct(
        protected MaintenanceModeService $maintenanceService,
    ) {}

    /**
     * 获取当前维护模式状态
     */
    public function status(): \Illuminate\Http\JsonResponse
    {
        $active = $this->maintenanceService->isActive();
        $config = $this->maintenanceService->getConfig();

        return ApiResponse::success([
            'is_active' => $active,
            'config' => $config,
            'maintenance' => $active ? $this->maintenanceService->getMaintenanceData() : null,
        ]);
    }

    /**
     * 启用维护模式
     */
    public function enable(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:200',
            'message' => 'nullable|string',
            'whitelist_ips' => 'nullable|array',
            'whitelist_ips.*' => 'string',
            'whitelist_paths' => 'nullable|array',
            'whitelist_paths.*' => 'string',
            'scheduled_end_at' => 'nullable|date',
            'auto_disable_at' => 'nullable|date|after:now',
            'retry_after' => 'nullable|integer|min:5|max:86400',
            'system_maintenance' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $config = $this->maintenanceService->enable($validator->validated());

        return ApiResponse::success($config, '维护模式已启用');
    }

    /**
     * 禁用维护模式
     */
    public function disable(): \Illuminate\Http\JsonResponse
    {
        $this->maintenanceService->disable();

        return ApiResponse::success(null, '维护模式已关闭');
    }

    /**
     * 更新维护模式配置
     */
    public function update(Request $request, MaintenanceConfig $maintenanceConfig): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:200',
            'message' => 'nullable|string',
            'whitelist_ips' => 'nullable|array',
            'whitelist_ips.*' => 'string',
            'whitelist_paths' => 'nullable|array',
            'whitelist_paths.*' => 'string',
            'scheduled_end_at' => 'nullable|date',
            'auto_disable_at' => 'nullable|date',
            'retry_after' => 'nullable|integer|min:5|max:86400',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $maintenanceConfig->update(array_merge(
            $validator->validated(),
            ['updated_by' => $request->user()?->id],
        ));

        $this->maintenanceService->clearCache();

        return ApiResponse::success($maintenanceConfig->fresh(), '维护配置已更新');
    }

    /**
     * 获取历史记录
     */
    public function history(): \Illuminate\Http\JsonResponse
    {
        $history = MaintenanceConfig::orderBy('created_at', 'desc')
            ->paginate(20);

        return ApiResponse::paginated($history);
    }
}
