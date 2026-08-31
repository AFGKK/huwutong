<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FailoverRule;
use App\Services\MultiRegionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MultiRegionController extends Controller
{
    public function __construct(
        protected MultiRegionService $service,
    ) {}

    // ═══════════ 数据中心管理 ═══════════

    public function listDataCenters(Request $request): JsonResponse
    {
        $data = $this->service->listDataCenters(
            $request->only(['region', 'status', 'is_active'])
        );
        return ApiResponse::success($data);
    }

    public function showDataCenter(int $id): JsonResponse
    {
        $dc = \App\Models\DataCenter::with('latestHealthLog')->findOrFail($id);
        return ApiResponse::success($dc);
    }

    public function storeDataCenter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:data_centers,code',
            'region' => 'required|string|in:asia,europe,us,oceania,africa,south_america',
            'country_code' => 'nullable|string|max:5',
            'city' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'base_url' => 'nullable|string|max:255',
            'health_check_url' => 'nullable|string|max:255',
            'capabilities' => 'nullable|array',
            'capabilities.*' => 'string|in:compute,storage,database,cache,queue',
            'status' => 'nullable|string|in:healthy,degraded,down,maintenance',
        ]);

        $dc = $this->service->createDataCenter($validated);
        return ApiResponse::success($dc, __('app.api.multi_region.dc_created'), 201);
    }

    public function updateDataCenter(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'code' => 'nullable|string|max:50|unique:data_centers,code,' . $id,
            'region' => 'nullable|string|in:asia,europe,us,oceania,africa,south_america',
            'country_code' => 'nullable|string|max:5',
            'city' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'base_url' => 'nullable|string|max:255',
            'health_check_url' => 'nullable|string|max:255',
            'capabilities' => 'nullable|array',
            'capabilities.*' => 'string|in:compute,storage,database,cache,queue',
            'status' => 'nullable|string|in:healthy,degraded,down,maintenance',
        ]);

        $dc = $this->service->updateDataCenter($id, $validated);
        return ApiResponse::success($dc, __('app.api.multi_region.dc_updated'));
    }

    public function destroyDataCenter(int $id): JsonResponse
    {
        $this->service->deleteDataCenter($id);
        return ApiResponse::success(null, __('app.api.multi_region.dc_deleted'));
    }

    public function seedDataCenters(): JsonResponse
    {
        $created = $this->service->seedDefaultDataCenters();
        return ApiResponse::success($created, __('app.api.multi_region.dc_initialized'));
    }

    // ═══════════ 健康检查 ═══════════

    public function healthCheck(int $id): JsonResponse
    {
        $dc = \App\Models\DataCenter::findOrFail($id);
        $log = $this->service->performHealthCheck($dc);
        return ApiResponse::success($log, __('app.api.multi_region.health_done'));
    }

    public function healthCheckAll(): JsonResponse
    {
        $results = $this->service->healthCheckAll();
        return ApiResponse::success($results, __('app.api.multi_region.health_all_done'));
    }

    public function healthTrend(int $id, Request $request): JsonResponse
    {
        $hours = $request->input('hours', 24);
        $data = $this->service->getHealthTrend($id, (int)$hours);
        return ApiResponse::success($data);
    }

    // ═══════════ 故障切换规则 ═══════════

    public function listFailoverRules(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $data = $this->service->listFailoverRules(
            $tenantId,
            $request->only(['status'])
        );
        return ApiResponse::success($data);
    }

    public function showFailoverRule(FailoverRule $failoverRule): JsonResponse
    {
        $failoverRule->load(['primaryDc', 'backupDc']);
        return ApiResponse::success($failoverRule);
    }

    public function storeFailoverRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'primary_dc_id' => 'required|integer|exists:data_centers,id',
            'backup_dc_id' => 'required|integer|exists:data_centers,id|different:primary_dc_id',
            'trigger_type' => 'nullable|string|in:latency,down,manual',
            'trigger_threshold_ms' => 'nullable|numeric|min:1|max:10000',
            'failure_count_threshold' => 'nullable|integer|min:1|max:20',
            'auto_failover' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $rule = $this->service->createFailoverRule($tenantId, $validated);
        $rule->load(['primaryDc:id,name,code', 'backupDc:id,name,code']);
        return ApiResponse::success($rule, __('app.api.multi_region.failover_created'), 201);
    }

    public function updateFailoverRule(Request $request, FailoverRule $failoverRule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'primary_dc_id' => 'nullable|integer|exists:data_centers,id',
            'backup_dc_id' => 'nullable|integer|exists:data_centers,id|different:primary_dc_id',
            'trigger_type' => 'nullable|string|in:latency,down,manual',
            'trigger_threshold_ms' => 'nullable|numeric|min:1|max:10000',
            'failure_count_threshold' => 'nullable|integer|min:1|max:20',
            'auto_failover' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,failover,restoring,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        $rule = $this->service->updateFailoverRule($failoverRule->id, $validated);
        $rule->load(['primaryDc:id,name,code', 'backupDc:id,name,code']);
        return ApiResponse::success($rule, __('app.api.multi_region.failover_updated'));
    }

    public function destroyFailoverRule(FailoverRule $failoverRule): JsonResponse
    {
        $this->service->deleteFailoverRule($failoverRule->id);
        return ApiResponse::success(null, __('app.api.multi_region.failover_deleted'));
    }

    // ═══════════ 执行故障切换 ═══════════

    public function executeFailover(Request $request, FailoverRule $failoverRule): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $log = $this->service->executeFailover($failoverRule, $validated['reason'], false);
        return ApiResponse::success($log, __('app.api.multi_region.failover_executed'));
    }

    public function executeRestore(Request $request, FailoverRule $failoverRule): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $log = $this->service->executeRestore($failoverRule, $validated['reason']);
        return ApiResponse::success($log, __('app.api.multi_region.restored_primary'));
    }

    // ═══════════ 故障切换日志 ═══════════

    public function listFailoverLogs(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $data = $this->service->listFailoverLogs(
            $tenantId,
            $request->only(['action', 'rule_id', 'date_from', 'date_to']),
            $request->input('per_page', 20)
        );
        return ApiResponse::success($data);
    }

    // ═══════════ 自动故障切换检测 ═══════════

    public function autoFailoverCheck(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $results = $this->service->autoFailoverCheck($tenantId);
        return ApiResponse::success([
            'actions_taken' => $results,
            'total' => count($results),
        ]);
    }

    // ═══════════ M3-52 区域部署管理 ═══════════

    public function listRegionDeployments(Request $request): JsonResponse
    {
        $data = $this->service->listRegionDeployments(
            $request->only(['status', 'provider', 'is_primary'])
        );
        return ApiResponse::success($data);
    }

    public function showRegionDeployment(int $id): JsonResponse
    {
        $data = $this->service->showRegionDeployment($id);
        return ApiResponse::success($data);
    }

    public function storeRegionDeployment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'region_key' => 'required|string|max:50|unique:region_deployments,region_key',
            'name' => 'required|string|max:100',
            'provider' => 'nullable|string|max:30',
            'api_url' => 'required|string|max:500',
            'status' => 'nullable|string|in:active,degraded,inactive',
            'is_primary' => 'nullable|boolean',
            'weight' => 'nullable|integer|min:0|max:10000',
            'version' => 'nullable|string|max:50',
            'config' => 'nullable|array',
        ]);

        $deployment = $this->service->createRegionDeployment($validated);
        return ApiResponse::success($deployment, __('app.api.multi_region.deployment_created'), 201);
    }

    public function updateRegionDeployment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'provider' => 'nullable|string|max:30',
            'api_url' => 'nullable|string|max:500',
            'status' => 'nullable|string|in:active,degraded,inactive',
            'is_primary' => 'nullable|boolean',
            'weight' => 'nullable|integer|min:0|max:10000',
            'is_healthy' => 'nullable|boolean',
            'version' => 'nullable|string|max:50',
            'config' => 'nullable|array',
        ]);

        $deployment = $this->service->updateRegionDeployment($id, $validated);
        return ApiResponse::success($deployment, __('app.api.multi_region.deployment_updated'));
    }

    public function destroyRegionDeployment(int $id): JsonResponse
    {
        $this->service->deleteRegionDeployment($id);
        return ApiResponse::success(null, __('app.api.multi_region.deployment_deleted'));
    }

    public function seedRegionDeployments(): JsonResponse
    {
        $created = $this->service->seedRegionDeployments();
        return ApiResponse::success($created, __('app.api.multi_region.tri_region_init'));
    }

    // ═══════════ M3-52 跨区域数据同步 ═══════════

    public function startDataSync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_region' => 'required|string|exists:region_deployments,region_key',
            'target_region' => 'required|string|exists:region_deployments,region_key|different:source_region',
            'data_type' => 'required|string|in:license,customer,product,audit_log',
        ]);

        $syncLog = $this->service->startDataSync(
            $validated['source_region'],
            $validated['target_region'],
            $validated['data_type']
        );
        return ApiResponse::success($syncLog, __('app.api.multi_region.sync_started'));
    }

    public function listSyncLogs(Request $request): JsonResponse
    {
        $data = $this->service->listSyncLogs(
            $request->only(['status', 'data_type', 'source_region', 'date_from', 'date_to']),
            $request->input('per_page', 20)
        );
        return ApiResponse::success($data);
    }

    // ═══════════ M3-52 区域健康检查（新表） ═══════════

    public function checkAllRegionHealth(): JsonResponse
    {
        $results = $this->service->checkAllRegionHealth();
        return ApiResponse::success($results, __('app.api.multi_region.region_health_done'));
    }

    public function regionHealthTrend(string $regionKey, Request $request): JsonResponse
    {
        $hours = $request->input('hours', 24);
        $data = $this->service->getRegionHealthTrend($regionKey, (int)$hours);
        return ApiResponse::success($data);
    }

    public function crossRegionHealthCheck(): JsonResponse
    {
        $results = $this->service->crossRegionHealthCheck();
        return ApiResponse::success($results, __('app.api.multi_region.cross_check_done'));
    }

    // ═══════════ M3-52 GeoDNS路由 ═══════════

    public function getOptimalRegion(Request $request): JsonResponse
    {
        $clientIp = $request->input('client_ip', $request->ip());
        $result = $this->service->getOptimalRegion($clientIp);
        return ApiResponse::success($result);
    }

    // ═══════════ 仪表盘 ═══════════

    public function dashboard(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $data = $this->service->getDashboard($tenantId);
        return ApiResponse::success($data);
    }
}
