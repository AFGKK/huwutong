<?php

namespace App\Services;

use App\Models\CrossTenantShare;
use App\Models\IsolationAuditLog;
use App\Models\QuotaPlan;
use App\Models\Tenant;
use App\Models\TenantUsageSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 租户隔离增强服务
 *
 * 提供：
 * - 资源配额管理（计划、覆盖、检查）
 * - 数据隔离审计日志
 * - 跨租户资源共享
 * - 用量快照与统计
 */
class TenantIsolationService
{
    // ─── 配额方案管理 ───

    public function getQuotaPlans(): array
    {
        return QuotaPlan::orderBy('price_monthly')->get()->all();
    }

    public function createQuotaPlan(array $data): QuotaPlan
    {
        return QuotaPlan::create($data);
    }

    public function updateQuotaPlan(QuotaPlan $plan, array $data): QuotaPlan
    {
        $plan->update($data);
        return $plan->fresh();
    }

    public function deleteQuotaPlan(QuotaPlan $plan): void
    {
        // 检查是否有租户在使用
        if ($plan->tenants()->count() > 0) {
throw new \RuntimeException(__("app.tenant_isolation.plan_has_tenants", ['count' => $plan->tenants()->count()]));
        }
        $plan->delete();
    }

    // ─── 租户配额管理 ───

    /**
     * 获取租户的有效配额（方案 + 覆盖）
     */
    public function getEffectiveQuota(Tenant $tenant): array
    {
        $defaults = QuotaPlan::defaultLimits();
        $plan = $tenant->quotaPlan;

        $limits = [];
        $allKeys = array_keys($defaults);
        foreach ($allKeys as $key) {
            $limits[$key] = $defaults[$key];
        }

        // 方案覆盖
        if ($plan && !empty($plan->limits)) {
            foreach ($plan->limits as $key => $value) {
                if ($value !== null) $limits[$key] = (int) $value;
            }
        }

        // 租户级覆盖
        if (!empty($tenant->quota_overrides)) {
            foreach ($tenant->quota_overrides as $key => $value) {
                if ($value !== null) $limits[$key] = (int) $value;
            }
        }

        // 如果租户上有直接字段，也覆盖
        $directFields = [
            'max_users' => 'users_max',
            'max_licenses' => 'licenses_max',
            'max_devices' => 'devices_max',
            'max_api_keys' => 'api_keys_max',
            'storage_limit_mb' => 'storage_mb',
            'monthly_api_limit' => 'monthly_api_calls',
        ];
        foreach ($directFields as $field => $key) {
            if ($tenant->$field !== null) {
                $limits[$key] = (int) $tenant->$field;
            }
        }

        return $limits;
    }

    /**
     * 更新租户配额
     */
    public function updateTenantQuota(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        $this->logEvent($tenant->id, 'config_change', 'info', 'quota', [
            'action' => 'update_quota',
            'changes' => $data,
        ]);

        return $tenant->fresh();
    }

    /**
     * 检查租户是否超过配额
     */
    public function checkTenantQuota(Tenant $tenant, string $resourceType, int $requestedAmount = 1): array
    {
        if (!$tenant->quota_check_enabled) {
            return ['allowed' => true];
        }

        $limits = $this->getEffectiveQuota($tenant);
        $usage = $this->getCurrentUsage($tenant);

        $resourceKey = match ($resourceType) {
            'licenses' => 'licenses_max',
            'devices' => 'devices_max',
            'users' => 'users_max',
            'api_keys' => 'api_keys_max',
            'storage_mb' => 'storage_mb',
            'monthly_api_calls' => 'monthly_api_calls',
            default => null,
        };

        if (!$resourceKey || !isset($limits[$resourceKey])) {
            return ['allowed' => true];
        }

        $current = $usage[$resourceKey] ?? 0;
        $limit = (int) $limits[$resourceKey];
        $remaining = $limit - $current;
        $percent = $limit > 0 ? round(($current / $limit) * 100, 2) : 0;

        $overQuota = $remaining < $requestedAmount;

        if ($overQuota) {
            $action = $tenant->over_quota_action ?? 'block';

            if ($action !== 'log') {
                $this->logEvent($tenant->id, 'quota_breach', 'warning', $resourceType, [
                    'current' => $current,
                    'limit' => $limit,
                    'requested' => $requestedAmount,
                    'action_taken' => $action,
                ]);

                // 更新超额状态
                if (!$tenant->over_quota_since) {
                    $tenant->update(['over_quota_since' => now()]);
                }
            }

            // 告警阈值通知
            $notifyAt = $tenant->notify_quota_at ?? 80;
            if ($percent >= $notifyAt && $action !== 'warn') {
                // 仅在超过阈值时记录通知
                $lastNotified = $tenant->quota_last_notified_at;
                if (!$lastNotified || $lastNotified->diffInHours(now()) >= 24) {
                    $this->logEvent($tenant->id, 'quota_notify', 'info', $resourceType, [
                        'current' => $current,
                        'limit' => $limit,
                        'percent' => $percent,
                        'threshold' => $notifyAt,
                    ]);
                    $tenant->update(['quota_last_notified_at' => now()]);
                }
            }

            if ($action === 'block') {
                return ['allowed' => false, 'current' => $current, 'limit' => $limit, 'percent' => $percent];
            }

            if ($action === 'warn') {
                return ['allowed' => true, 'warn' => true, 'current' => $current, 'limit' => $limit, 'percent' => $percent];
            }
        }

        return ['allowed' => true, 'current' => $current, 'limit' => $limit, 'percent' => $percent];
    }

    /**
     * 获取当前用量（带缓存）
     */
    public function getCurrentUsage(Tenant $tenant): array
    {
        $usage = [];

        $usage['licenses_max'] = $tenant->licenses()->count();
        $usage['devices_max'] = $tenant->devices()->count();
        $usage['users_max'] = $tenant->users()->count();
        $usage['api_keys_max'] = \App\Models\ApiKey::where('tenant_id', $tenant->id)->count();
        $usage['storage_mb'] = 0; // 需要文件系统计算
        $usage['monthly_api_calls'] = \App\Models\Log::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return $usage;
    }

    /**
     * 刷新用量快照
     */
    public function refreshUsageSnapshot(Tenant $tenant): void
    {
        $usage = $this->getCurrentUsage($tenant);
        $limits = $this->getEffectiveQuota($tenant);
        $now = now();

        foreach ($limits as $key => $limit) {
            $current = $usage[$key] ?? 0;
            $percent = $limit > 0 ? round(($current / $limit) * 100, 2) : 0;

            TenantUsageSnapshot::updateOrCreate(
                ['tenant_id' => $tenant->id, 'metric_key' => $key, 'period' => 'current'],
                [
                    'current_usage' => $current,
                    'quota_limit' => $limit,
                    'usage_percent' => $percent,
                    'snapshot_at' => $now,
                ]
            );
        }

        // 更新租户 usage_metrics
        $tenant->update(['usage_metrics' => $usage, 'usage_metrics' => array_merge($tenant->usage_metrics ?? [], $usage)]);
    }

    // ─── 隔离审计日志 ───

    public function logEvent(int $tenantId, string $eventType, string $severity, ?string $resourceType, array $details = []): IsolationAuditLog
    {
        return IsolationAuditLog::create([
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'severity' => $severity,
            'resource_type' => $resourceType,
            'details' => $details,
        ]);
    }

    public function getAuditLogs(int $tenantId, array $filters = []): array
    {
        $query = IsolationAuditLog::where('tenant_id', $tenantId);

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (isset($filters['is_resolved'])) {
            $query->where('is_resolved', $filters['is_resolved']);
        }

        return $query->orderByDesc('created_at')
            ->limit(min((int) ($filters['per_page'] ?? 50), 200))
            ->get()
            ->all();
    }

    public function resolveAuditLog(int $logId): IsolationAuditLog
    {
        $log = IsolationAuditLog::findOrFail($logId);
        $log->update(['is_resolved' => true, 'resolved_at' => now()]);
        return $log->fresh();
    }

    // ─── 跨租户共享 ───

    public function getShares(int $tenantId, string $direction = 'outgoing'): array
    {
        if ($direction === 'incoming') {
            return CrossTenantShare::where('target_tenant_id', $tenantId)
                ->with('sourceTenant')
                ->orderByDesc('created_at')
                ->get()
                ->all();
        }

        return CrossTenantShare::where('source_tenant_id', $tenantId)
            ->with('targetTenant')
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function createShare(array $data): CrossTenantShare
    {
        return CrossTenantShare::create($data);
    }

    public function revokeShare(int $shareId): void
    {
        $share = CrossTenantShare::findOrFail($shareId);
        $share->update(['status' => 'revoked']);
    }

    // ─── 仪表盘 ───

    public function getDashboard(): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $quotaPlans = QuotaPlan::count();
        $auditLogs = IsolationAuditLog::count();
        $pendingBreaches = IsolationAuditLog::where('event_type', 'quota_breach')
            ->where('is_resolved', false)
            ->count();
        $sharesActive = CrossTenantShare::where('status', 'active')->count();

        // 按方案统计租户分布
        $planDist = QuotaPlan::withCount('tenants')
            ->orderBy('tier')
            ->get()
            ->map(fn($p) => ['name' => $p->name, 'count' => $p->tenants_count])
            ->all();

        // 按配额使用率统计
        $overQuota = Tenant::whereNotNull('over_quota_since')->count();
        $nearQuota = IsolationAuditLog::where('event_type', 'quota_notify')
            ->where('is_resolved', false)
            ->distinct('tenant_id')
            ->count('tenant_id');

        return [
            'stats' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'total_quota_plans' => $quotaPlans,
                'total_audit_logs' => $auditLogs,
                'pending_breaches' => $pendingBreaches,
                'active_shares' => $sharesActive,
                'over_quota_tenants' => $overQuota,
                'near_quota_tenants' => $nearQuota,
            ],
            'plan_distribution' => $planDist,
        ];
    }

    /**
     * 批量刷新所有租户用量快照
     */
    public function refreshAllSnapshots(): int
    {
        $count = 0;
        Tenant::chunk(50, function ($tenants) use (&$count) {
            foreach ($tenants as $tenant) {
                try {
                    $this->refreshUsageSnapshot($tenant);
                    $count++;
                } catch (\Exception $e) {
                    Log::warning("刷新租户 {$tenant->id} 用量失败: {$e->getMessage()}");
                }
            }
        });
        return $count;
    }
}
