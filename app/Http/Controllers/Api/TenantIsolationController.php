<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CrossTenantShare;
use App\Models\IsolationAuditLog;
use App\Models\QuotaPlan;
use App\Models\Tenant;
use App\Services\TenantIsolationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TenantIsolationController extends Controller
{
    public function __construct(
        protected TenantIsolationService $isolation,
    ) {}

    // ─── 仪表盘 ───

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->isolation->getDashboard());
    }

    // ─── 配额方案 ───

    public function quotaPlans(): JsonResponse
    {
        return ApiResponse::success($this->isolation->getQuotaPlans());
    }

    public function createQuotaPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:quota_plans,slug',
            'description' => 'nullable|string',
            'limits' => 'required|array',
            'features' => 'nullable|array',
            'tier' => 'required|string|in:free,starter,business,enterprise,custom',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.tenant_iso.validation_failed'), $validator->errors()->toArray());
        }

        $plan = $this->isolation->createQuotaPlan($validator->validated());
        return ApiResponse::created($plan, __('app.api.tenant_iso.quota_plan_created'));
    }

    public function updateQuotaPlan(Request $request, int $id): JsonResponse
    {
        $plan = QuotaPlan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'slug' => 'sometimes|string|max:100|unique:quota_plans,slug,' . $id,
            'description' => 'nullable|string',
            'limits' => 'sometimes|array',
            'features' => 'nullable|array',
            'tier' => 'sometimes|string|in:free,starter,business,enterprise,custom',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.tenant_iso.validation_failed'), $validator->errors()->toArray());
        }

        $plan = $this->isolation->updateQuotaPlan($plan, $validator->validated());
        return ApiResponse::success($plan, __('app.api.tenant_iso.quota_plan_updated'));
    }

    public function deleteQuotaPlan(int $id): JsonResponse
    {
        $plan = QuotaPlan::findOrFail($id);

        try {
            $this->isolation->deleteQuotaPlan($plan);
            return ApiResponse::success(null, __('app.api.tenant_iso.quota_plan_deleted'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('PLAN_IN_USE', $e->getMessage(), 409);
        }
    }

    // ─── 租户配额 ───

    public function tenantQuota(int $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $limits = $this->isolation->getEffectiveQuota($tenant);
        $usage = $this->isolation->getCurrentUsage($tenant);

        $quota = [];
        foreach ($limits as $key => $limit) {
            $current = $usage[$key] ?? 0;
            $quota[] = [
                'metric_key' => $key,
                'limit' => $limit,
                'current' => $current,
                'percent' => $limit > 0 ? round(($current / $limit) * 100, 2) : 0,
            ];
        }

        return ApiResponse::success([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'quota_plan' => $tenant->quotaPlan?->name,
            'quota' => $quota,
        ]);
    }

    public function updateTenantQuota(Request $request, int $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        $validator = Validator::make($request->all(), [
            'quota_plan_id' => 'nullable|exists:quota_plans,id',
            'quota_overrides' => 'nullable|array',
            'max_users' => 'nullable|integer|min:0',
            'max_licenses' => 'nullable|integer|min:0',
            'max_devices' => 'nullable|integer|min:0',
            'max_api_keys' => 'nullable|integer|min:0',
            'storage_limit_mb' => 'nullable|integer|min:0',
            'monthly_api_limit' => 'nullable|integer|min:0',
            'data_retention_days' => 'nullable|integer|min:1',
            'notify_quota_at' => 'nullable|integer|min:0|max:100',
            'quota_check_enabled' => 'nullable|boolean',
            'over_quota_action' => 'nullable|string|in:block,warn,log',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.tenant_iso.validation_failed'), $validator->errors()->toArray());
        }

        $tenant = $this->isolation->updateTenantQuota($tenant, $validator->validated());

        // 刷新快照
        $this->isolation->refreshUsageSnapshot($tenant);

        return ApiResponse::success($tenant, __('app.api.tenant_iso.tenant_quota_updated'));
    }

    public function refreshTenantUsage(int $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $this->isolation->refreshUsageSnapshot($tenant);
        return ApiResponse::success(null, __('app.api.tenant_iso.usage_refreshed'));
    }

    // ─── 隔离审计日志 ───

    public function auditLogs(Request $request, int $tenantId): JsonResponse
    {
        $filters = $request->only(['event_type', 'severity', 'is_resolved', 'per_page']);
        return ApiResponse::success(
            $this->isolation->getAuditLogs($tenantId, $filters)
        );
    }

    public function resolveAuditLog(int $id): JsonResponse
    {
        $log = $this->isolation->resolveAuditLog($id);
        return ApiResponse::success($log, __('app.api.tenant_iso.alert_resolved'));
    }

    // ─── 跨租户共享 ───

    public function shares(Request $request, int $tenantId): JsonResponse
    {
        $direction = $request->input('direction', 'outgoing');
        return ApiResponse::success(
            $this->isolation->getShares($tenantId, $direction)
        );
    }

    public function createShare(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_tenant_id' => 'required|exists:tenants,id',
            'target_tenant_id' => 'required|exists:tenants,id|different:source_tenant_id',
            'resource_type' => 'required|string|in:' . implode(',', CrossTenantShare::RESOURCE_TYPES),
            'resource_id' => 'nullable|integer',
            'permission' => 'nullable|string|in:' . implode(',', CrossTenantShare::PERMISSIONS),
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.tenant_iso.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['status'] = 'active';

        // 检查是否已存在
        $existing = CrossTenantShare::where('source_tenant_id', $data['source_tenant_id'])
            ->where('target_tenant_id', $data['target_tenant_id'])
            ->where('resource_type', $data['resource_type'])
            ->where('resource_id', $data['resource_id'])
            ->first();

        if ($existing) {
            $existing->update(['status' => 'active', 'permission' => $data['permission'] ?? 'read']);
            return ApiResponse::success($existing->fresh(), __('app.api.tenant_iso.share_updated'));
        }

        $share = $this->isolation->createShare($data);
        return ApiResponse::created($share, __('app.api.tenant_iso.share_created'));
    }

    public function revokeShare(int $id): JsonResponse
    {
        $this->isolation->revokeShare($id);
        return ApiResponse::success(null, __('app.api.tenant_iso.share_revoked'));
    }

    // ─── 隔离配置批量操作 ───

    public function updateIsolationLevel(Request $request, int $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        $validator = Validator::make($request->all(), [
            'isolation_level' => 'required|string|in:strict,logical,shared',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.tenant_iso.validation_failed'), $validator->errors()->toArray());
        }

        $tenant->update(['isolation_level' => $request->input('isolation_level')]);

        $this->isolation->logEvent($tenant->id, 'isolation_change', 'info', 'tenant', [
            'new_level' => $request->input('isolation_level'),
            'changed_by' => $request->user()->id,
        ]);

        return ApiResponse::success($tenant->fresh(), __('app.api.tenant_iso.isolation_updated'));
    }

    // ─── 批量刷新 ───

    public function batchRefresh(): JsonResponse
    {
        $count = $this->isolation->refreshAllSnapshots();
        return ApiResponse::success(['refreshed' => $count], __('app.api.tenant_iso.quota_refreshed', ['count' => $count]));
    }
}
