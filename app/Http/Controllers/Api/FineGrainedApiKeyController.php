<?php

namespace App\Http\Controllers\Api;

use App\Enums\ErrorCode;
use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\FineGrainedApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API Key 端点级细粒度权限管理控制器 (M2-138)
 *
 * 提供管理后台可视化配置端点：
 * - 端点级权限配置（activate/validate/revoke/check × GET/POST）
 * - 有效期精确到小时
 * - 用量配额查询
 * - SDK 端点元数据
 */
class FineGrainedApiKeyController extends Controller
{
    protected FineGrainedApiKeyService $fineGrainedService;

    public function __construct(FineGrainedApiKeyService $fineGrainedService)
    {
        $this->fineGrainedService = $fineGrainedService;
    }

    /**
     * 获取 SDK 端点元数据（供可视化配置页面使用）
     */
    public function sdkEndpoints(): JsonResponse
    {
        return ApiResponse::success([
            'endpoints' => $this->fineGrainedService->getSdkEndpoints(),
        ]);
    }

    /**
     * 获取指定 API Key 的端点权限配置
     */
    public function keyPermissions(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        return ApiResponse::success([
            'endpoint_permissions' => $this->fineGrainedService->getKeyEndpointPermissions($apiKey),
            'expiry_status' => $this->fineGrainedService->getExpiryStatus($apiKey),
            'quota_snapshot' => $this->fineGrainedService->getQuotaSnapshot($apiKey),
            'allowed_ips' => $apiKey->allowed_ips ?? [],
            'allowed_methods' => $apiKey->allowed_methods,
            'permissions' => $apiKey->permissions,
            'tier' => $apiKey->tier,
        ]);
    }

    /**
     * 更新 API Key 的端点权限配置
     */
    public function updatePermissions(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        $validator = Validator::make($request->all(), [
            'endpoint_permissions' => 'sometimes|nullable|array',
            'endpoint_permissions.*' => 'array',
            'allowed_endpoints' => 'sometimes|nullable|array',
            'allowed_endpoints.*' => 'string|max:200',
            'allowed_methods' => 'sometimes|nullable|string',
            'allowed_ips' => 'sometimes|nullable|array',
            'allowed_ips.*' => 'ip',
            'expires_at' => 'sometimes|nullable|date|after:now',
            'usage_quota' => 'sometimes|nullable|integer|min:1',
            'daily_quota' => 'sometimes|nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__("app.fine_grained_api_key.msg_e441b11e"), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $oldValues = $apiKey->toArray();

        // 处理端点细粒度权限
        if (isset($data['endpoint_permissions'])) {
            $result = $this->fineGrainedService->updateEndpointPermissions(
                $apiKey,
                $data['endpoint_permissions']
            );

            if (! $result['success']) {
                return ApiResponse::error(
                    ErrorCode::VALIDATION_ERROR,
                    __('app.fine_grained_api_key.invalid_endpoint_perms'),
                    422,
                    $result['errors']
                );
            }
        }

        // 更新其他字段
        $updatable = array_intersect_key($data, array_flip([
            'allowed_endpoints', 'allowed_methods', 'allowed_ips',
            'expires_at', 'usage_quota', 'daily_quota',
        ]));

        if (! empty($updatable)) {
            $apiKey->update($updatable);
        }

        // 审计
        $newValues = $apiKey->fresh()->toArray();
        $apiKey->logAction('update_permissions', 'user', $request->user()->id, $oldValues, $newValues);

        return ApiResponse::success([
            'key' => $apiKey->fresh(),
            'endpoint_permissions' => $this->fineGrainedService->getKeyEndpointPermissions($apiKey),
        ], __('app.fine_grained_api_key.endpoint_perms_updated'));
    }

    /**
     * 获取 API Key 的用量统计概览（按端点维度）
     */
    public function keyUsageStats(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorizeKey($request, $apiKey);

        return ApiResponse::success([
            'quota' => $this->fineGrainedService->getQuotaSnapshot($apiKey),
            'expiry' => $this->fineGrainedService->getExpiryStatus($apiKey),
            'allowed_endpoints' => $apiKey->getAllowedEndpointsList(),
        ]);
    }

    /**
     * 验证当前用户是否拥有此 API Key
     */
    protected function authorizeKey(Request $request, ApiKey $apiKey): void
    {
        $tenantId = $request->user()->tenant_id;

        if ($apiKey->tenant_id !== $tenantId) {
            abort(403, __("app.fine_grained_api_key.msg_b7839d18"));
        }
    }
}
