<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\LocalProxyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 本地 License 代理管理（云端管理接口）
 */
class LocalProxyController extends Controller
{
    public function __construct(
        protected LocalProxyService $localProxyService,
    ) {}

    /**
     * 仪表盘统计
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success(
            $this->localProxyService->getDashboardStats($tenantId)
        );
    }

    /**
     * 代理节点列表
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success(
            $this->localProxyService->getNodes($tenantId)
        );
    }

    /**
     * 注册代理节点
     */
    public function register(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'base_url' => 'nullable|url|max:255',
            'version' => 'nullable|string|max:20',
            'os' => 'nullable|string|max:50',
            'architecture' => 'nullable|string|max:20',
            'capabilities' => 'nullable|array',
            'capabilities.*' => 'string|in:offline_auth,heartbeat,crl_sync,cache',
        ]);

        $result = $this->localProxyService->registerNode($tenantId, $validated);

        return ApiResponse::success($result, __("app.local_proxy.msg_47b2ed5c"), 201);
    }

    /**
     * 激活代理节点（审批）
     */
    public function activate(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'node_id' => 'required|string',
            'register_token' => 'required|string',
        ]);

        $result = $this->localProxyService->activateNode(
            $tenantId, $validated['node_id'], $validated['register_token']
        );

        return ApiResponse::success($result, __("app.local_proxy.msg_691ffc07"));
    }

    /**
     * 节点详情
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success(
            $this->localProxyService->getNodeDetail($tenantId, $id)
        );
    }

    /**
     * 更新节点状态
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'status' => 'required|string|in:active,paused,decommissioned',
        ]);

        $node = $this->localProxyService->updateNodeStatus($tenantId, $id, $validated['status']);

        return ApiResponse::success($node, __("app.local_proxy.msg_045b1fa4"));
    }

    /**
     * 更新代理配置
     */
    public function updateConfig(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'sync_mode' => 'nullable|string|in:poll,push,hybrid',
            'sync_interval_seconds' => 'nullable|integer|min:30|max:86400',
            'heartbeat_interval_seconds' => 'nullable|integer|min:10|max:3600',
            'cache_ttl_seconds' => 'nullable|integer|min:300|max:604800',
            'max_cached_licenses' => 'nullable|integer|min:10|max:100000',
            'allow_offline_activation' => 'nullable|boolean',
            'require_cloud_validation' => 'nullable|boolean',
            'allowed_actions' => 'nullable|array',
            'allowed_actions.*' => 'string|in:validate,activate,deactivate',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'string',
        ]);

        $config = $this->localProxyService->updateNodeConfig($tenantId, $id, $validated);

        return ApiResponse::success($config, __("app.local_proxy.msg_ff1cfcd2"));
    }

    // ─── 代理内部 API（供代理节点调用） ───

    /**
     * 心跳（代理节点调用）
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $apiKey = $request->header('X-Proxy-API-Key');
        abort_unless($apiKey, 401, '缺少 API Key');

        $validated = $request->validate([
            'metrics' => 'nullable|array',
            'cache_stats' => 'nullable|array',
            'status' => 'nullable|string|in:healthy,degraded,offline',
            'error_message' => 'nullable|string|max:500',
        ]);

        $result = $this->localProxyService->processHeartbeat($apiKey, $validated);

        return response()->json($result);
    }

    /**
     * 获取配置（代理节点轮询调用）
     */
    public function config(Request $request): JsonResponse
    {
        $apiKey = $request->header('X-Proxy-API-Key');
        abort_unless($apiKey, 401, '缺少 API Key');

        return response()->json(
            $this->localProxyService->getNodeConfig($apiKey)
        );
    }

    /**
     * 离线验证 License（代理节点调用）
     */
    public function proxyValidate(Request $request): JsonResponse
    {
        $apiKey = $request->header('X-Proxy-API-Key');
        abort_unless($apiKey, 401, '缺少 API Key');

        $validated = $request->validate([
            'license_key' => 'required|string|max:64',
            'fingerprint' => 'nullable|string|max:255',
        ]);

        $result = $this->localProxyService->proxyValidate(
            $apiKey, $validated['license_key'], $validated['fingerprint'] ?? null
        );

        return response()->json($result);
    }

    /**
     * 同步激活日志（代理节点调用）
     */
    public function syncLogs(Request $request): JsonResponse
    {
        $apiKey = $request->header('X-Proxy-API-Key');
        abort_unless($apiKey, 401, '缺少 API Key');

        $validated = $request->validate([
            'logs' => 'required|array|max:1000',
            'logs.*.license_key' => 'required|string',
            'logs.*.action' => 'required|string|in:validate,activate,deactivate,offline_check',
            'logs.*.result' => 'required|string|in:allowed,denied,pending_sync',
            'logs.*.fingerprint' => 'nullable|string',
            'logs.*.reason' => 'nullable|string|max:100',
            'logs.*.client_ip' => 'nullable|string',
            'logs.*.metadata' => 'nullable|array',
        ]);

        $result = $this->localProxyService->syncActivationLogs($apiKey, $validated['logs']);

        return response()->json($result);
    }
}
