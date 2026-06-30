<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\RegionalComplianceConfig;
use App\Services\RegionalComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegionalComplianceController extends Controller
{
    public function __construct(protected RegionalComplianceService $service) {}

    /**
     * 合规仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 初始化区域合规配置
     */
    public function initialize(Request $request): JsonResponse
    {
        $this->service->initializeTenant($request->user()->tenant_id);
        return ApiResponse::success(null, '区域合规配置已初始化');
    }

    /**
     * 区域合规配置列表
     */
    public function configs(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->listConfigs($request->user()->tenant_id)
        );
    }

    /**
     * 更新区域合规配置
     */
    public function updateConfig(Request $request, string $regionKey): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'region_name' => 'nullable|string|max:100',
            'gdpr_enabled' => 'nullable|boolean',
            'pipl_enabled' => 'nullable|boolean',
            'vat_enabled' => 'nullable|boolean',
            'data_residency_enabled' => 'nullable|boolean',
            'cookie_consent_enabled' => 'nullable|boolean',
            'tax_reporting_enabled' => 'nullable|boolean',
            'tax_type' => 'nullable|string|max:30',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_reporting_frequency' => 'nullable|in:monthly,quarterly,yearly',
            'digital_service_tax' => 'nullable|boolean',
            'oss_enabled' => 'nullable|boolean',
            'oss_threshold' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $config = $this->service->updateConfig(
            $request->user()->tenant_id,
            $regionKey,
            $validator->validated()
        );

        return ApiResponse::success($config, '区域配置已更新');
    }

    /**
     * 合规状态检查
     */
    public function checkStatus(Request $request, string $regionKey): JsonResponse
    {
        return ApiResponse::success(
            $this->service->checkComplianceStatus($request->user()->tenant_id, $regionKey)
        );
    }

    /**
     * 销售限制列表
     */
    public function restrictions(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->listRestrictions(
                $request->user()->tenant_id,
                $request->input('region_key')
            )
        );
    }

    /**
     * 添加销售限制
     */
    public function addRestriction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'restrictable_type' => 'required|string|in:product,plan,sku',
            'restrictable_id' => 'required|integer',
            'region_key' => 'required|string|max:30',
            'is_allowed' => 'nullable|boolean',
            'restriction_type' => 'nullable|string|max:30',
            'restriction_value' => 'nullable|string|max:100',
            'reason' => 'nullable|string|max:500',
            'effective_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:effective_at',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $restriction = $this->service->addRestriction(
            $request->user()->tenant_id,
            $validator->validated()
        );

        return ApiResponse::created($restriction, '销售限制已添加');
    }

    /**
     * 删除销售限制
     */
    public function removeRestriction(Request $request, int $id): JsonResponse
    {
        $this->service->removeRestriction($id, $request->user()->tenant_id);
        return ApiResponse::success(null, '销售限制已移除');
    }

    /**
     * 检查产品区域销售资格
     */
    public function checkProductEligibility(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer',
            'region_key' => 'required|string|max:30',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        return ApiResponse::success(
            $this->service->checkProductSalesEligibility(
                $request->user()->tenant_id,
                $validator->validated()['product_id'],
                $validator->validated()['region_key']
            )
        );
    }

    /**
     * 生成合规报告摘要
     */
    public function generateSummary(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->generateComplianceSummary($request->user()->tenant_id)
        );
    }

    /**
     * 合规操作日志
     */
    public function logs(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getLogs($request->user()->tenant_id, $request->only([
                'region_key', 'action_type', 'status', 'date_from', 'date_to', 'per_page',
            ]))
        );
    }

    /**
     * 可用区域列表
     */
    public function availableRegions(): JsonResponse
    {
        return ApiResponse::success(config('compliance-regional.regions', []));
    }
}
