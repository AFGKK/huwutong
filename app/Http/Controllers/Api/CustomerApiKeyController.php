<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CustomerApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 客户 API Key 管理 (M2-96)
 *
 * 客户门户和管理后台的 API Key 管理
 */
class CustomerApiKeyController extends Controller
{
    public function __construct(
        protected CustomerApiKeyService $apiKeyService
    ) {}

    // ═══════════════════ 客户侧 ═══════════════════

    /** 我的 API Key 列表 */
    public function index(Request $request): JsonResponse
    {
        $keys = $this->apiKeyService->getKeys($request->user()->id, $request->only(['search', 'is_active', 'sort', 'per_page']));
        return ApiResponse::success($keys, __('app.api.customer_api_key.fetched'));
    }

    /** 仪表盘 */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->apiKeyService->getDashboard($request->user()->id), __('app.api.customer_api_key.fetched'));
    }

    /** 创建 API Key */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string|in:' . implode(',', array_keys(config('api-key.api_key.allowed_abilities', []))),
            'ip_whitelist' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $result = $this->apiKeyService->create($request->user()->id, $validated);

        return ApiResponse::success([
            'api_key' => $result['api_key'],
            'plain_text_key' => $result['plain_text_key'],
        ], __('app.api.customer_api_key.created'));
    }

    /** 更新 API Key */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string|in:' . implode(',', array_keys(config('api-key.api_key.allowed_abilities', []))),
            'ip_whitelist' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $apiKey = $this->apiKeyService->update($request->user()->id, $id, $validated);
        return ApiResponse::success($apiKey, __('app.api.customer_api_key.updated'));
    }

    /** 删除 API Key */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->apiKeyService->delete($request->user()->id, $id);
        return ApiResponse::success(null, __('app.api.customer_api_key.deleted'));
    }

    /** 切换启用/禁用 */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $apiKey = $this->apiKeyService->toggle($request->user()->id, $id);
        return ApiResponse::success($apiKey, $apiKey->is_active ? __('app.api.customer_api_key.enabled') : __('app.api.customer_api_key.disabled'));
    }

    /** 获取可用权限列表 */
    public function abilities(): JsonResponse
    {
        return ApiResponse::success(config('api-key.api_key.allowed_abilities', []), __('app.api.customer_api_key.fetched'));
    }

    // ═══════════════════ 管理端 ═══════════════════

    /** 管理端列表 */
    public function adminIndex(Request $request): JsonResponse
    {
        $keys = $this->apiKeyService->adminIndex($request->only(['user_id', 'search', 'is_active', 'per_page']));
        return ApiResponse::success($keys, __('app.api.customer_api_key.fetched'));
    }

    /** 管理端仪表盘 */
    public function adminDashboard(): JsonResponse
    {
        return ApiResponse::success($this->apiKeyService->adminDashboard(), __('app.api.customer_api_key.fetched'));
    }

    /** 管理端删除 */
    public function adminDestroy(int $id): JsonResponse
    {
        CustomerApiKey::findOrFail($id)->delete();
        return ApiResponse::success(null, __('app.api.customer_api_key.deleted'));
    }

    /** 管理端切换状态 */
    public function adminToggle(int $id): JsonResponse
    {
        $apiKey = CustomerApiKey::findOrFail($id);
        $apiKey->is_active = !$apiKey->is_active;
        $apiKey->save();
        return ApiResponse::success($apiKey->fresh(), $apiKey->is_active ? __('app.api.customer_api_key.enabled') : __('app.api.customer_api_key.disabled'));
    }
}
