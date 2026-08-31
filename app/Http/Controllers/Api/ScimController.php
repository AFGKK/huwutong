<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ScimConfig;
use App\Services\ScimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SCIM 自动用户同步管理 (M2-51)
 */
class ScimController extends Controller
{
    public function __construct(protected ScimService $scimService) {}

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->scimService->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 配置列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->scimService->getConfigs($request->user()->tenant_id)
        );
    }

    /**
     * 创建配置
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'provider' => 'required|string|in:generic,okta,azure,onelogin',
            'enabled' => 'boolean',
            'base_url' => 'required|url|max:500',
            'api_token' => 'required|string|max:500',
            'attribute_mapping' => 'nullable|array',
            'options' => 'nullable|array',
            'sync_frequency' => 'nullable|string|in:manual,hourly,daily,weekly',
        ]);

        $config = $this->scimService->saveConfig($request->user()->tenant_id, $validated);
        return ApiResponse::created($config);
    }

    /**
     * 更新配置
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'provider' => 'sometimes|string|in:generic,okta,azure,onelogin',
            'enabled' => 'sometimes|boolean',
            'base_url' => 'sometimes|url|max:500',
            'api_token' => 'sometimes|string|max:500',
            'attribute_mapping' => 'nullable|array',
            'options' => 'nullable|array',
            'sync_frequency' => 'nullable|string|in:manual,hourly,daily,weekly',
        ]);

        $config = $this->scimService->saveConfig($request->user()->tenant_id, $validated, $id);
        return ApiResponse::success($config, __("app.scim.msg_ff1cfcd2"));
    }

    /**
     * 删除配置
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->scimService->deleteConfig($request->user()->tenant_id, $id);
        return ApiResponse::success(null, __("app.scim.msg_02a1e7cd"));
    }

    /**
     * 测试连接
     */
    public function testConnection(Request $request, int $id): JsonResponse
    {
        $config = ScimConfig::where('id', $id)
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        $result = $this->scimService->testConnection($config);
        if ($result['success']) {
            return ApiResponse::success($result);
        }
        return ApiResponse::error($result['message'], 400);
    }

    /**
     * 执行同步
     */
    public function syncNow(Request $request, int $id): JsonResponse
    {
        $config = ScimConfig::where('id', $id)
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        if (!$config->enabled) {
            return ApiResponse::error(__("app.scim.msg_096660eb"), 400);
        }

        $log = $this->scimService->syncUsers($config);
        return ApiResponse::success($log, __("app.scim.msg_02f667de"));
    }

    /**
     * 同步日志
     */
    public function syncLogs(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(
            $this->scimService->getSyncLogs($request->user()->tenant_id, $id)
        );
    }

    /**
     * 获取 Provider 选项
     */
    public function providerOptions(Request $request, string $provider): JsonResponse
    {
        return ApiResponse::success(ScimService::getProviderOptions($provider));
    }

    /**
     * 获取默认属性映射
     */
    public function defaultMapping(): JsonResponse
    {
        return ApiResponse::success(ScimService::getDefaultAttributeMapping());
    }

    // ─── SCIM 标准端点（提供给 IdP 调用） ───

    /**
     * SCIM ServiceProviderConfig
     */
    public function scimServiceProviderConfig(): JsonResponse
    {
        return response()->json($this->scimService->getServiceProviderConfig());
    }

    /**
     * SCIM /Users 列表
     */
    public function scimListUsers(Request $request): JsonResponse
    {
        return response()->json(
            $this->scimService->listUsers($request->user()->tenant_id, $request->all())
        );
    }

    /**
     * SCIM /Users/{id}
     */
    public function scimGetUser(Request $request, int $userId): JsonResponse
    {
        $user = $this->scimService->getUser($request->user()->tenant_id, $userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        return response()->json($user);
    }

    /**
     * SCIM POST /Users
     */
    public function scimCreateUser(Request $request): JsonResponse
    {
        $user = $this->scimService->createUser($request->user()->tenant_id, $request->all());
        return response()->json($user, 201);
    }

    /**
     * SCIM PUT /Users/{id}
     */
    public function scimUpdateUser(Request $request, int $userId): JsonResponse
    {
        $user = $this->scimService->updateUser($request->user()->tenant_id, $userId, $request->all());
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        return response()->json($user);
    }

    /**
     * SCIM PATCH /Users/{id}
     */
    public function scimPatchUser(Request $request, int $userId): JsonResponse
    {
        $user = $this->scimService->patchUser($request->user()->tenant_id, $userId, $request->all());
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        return response()->json($user);
    }

    /**
     * SCIM DELETE /Users/{id}
     */
    public function scimDeleteUser(Request $request, int $userId): JsonResponse
    {
        $deleted = $this->scimService->deleteUser($request->user()->tenant_id, $userId);
        if (!$deleted) {
            return response()->json(['error' => 'User not found'], 404);
        }
        return response()->json(null, 204);
    }
}
