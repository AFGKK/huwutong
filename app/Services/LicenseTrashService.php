<?php

namespace App\Services;

use App\Models\License;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * License 回收站服务 (M2-13)
 *
 * 扩展 Laravel SoftDeletes，增加：
 * - 回收站列表查看（含删除者追踪）
 * - 永久删除
 * - 批量清空回收站
 * - 30天自动清理
 * - 回收站统计
 */
class LicenseTrashService
{
    /**
     * 获取回收站列表
     */
    public function getTrashed(int $tenantId, array $filters = []): array
    {
        $query = License::where('tenant_id', $tenantId)->onlyTrashed()->with(['product', 'customer']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('license_key', 'like', "%{$filters['search']}%")
                  ->orWhere('name', 'like', "%{$filters['search']}%");
            });
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('deleted_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('deleted_at', '<=', $filters['date_to']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->latest('deleted_at')->forPage($page, $perPage)->get();

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 从回收站恢复 License
     */
    public function restore(int $id): array
    {
        $license = License::onlyTrashed()->findOrFail($id);
        $license->restore();
        return ['success' => true, 'message' => __('app.common.license_restored_from_trash')];
    }

    /**
     * 批量恢复
     */
    public function batchRestore(int $tenantId, array $ids): array
    {
        $count = License::where('tenant_id', $tenantId)->onlyTrashed()
            ->whereIn('id', $ids)
            ->restore();

        return ['success' => true, 'message' => __('app.common.licenses_restored', ['count' => $count])];
    }

    /**
     * 永久删除（从回收站彻底移除）
     */
    public function forceDelete(int $id): array
    {
        $license = License::onlyTrashed()->findOrFail($id);
        $license->forceDelete();

        return ['success' => true, 'message' => __('app.common.license_permanently_deleted')];
    }

    /**
     * 清空回收站（按租户）
     */
    public function clearTrash(int $tenantId, ?Carbon $before = null): array
    {
        $query = License::where('tenant_id', $tenantId)->onlyTrashed();

        if ($before) {
            $query->where('deleted_at', '<', $before);
        }

        $count = $query->forceDelete();

        return ['success' => true, 'message' => __('app.common.expired_licenses_cleared', ['count' => $count]), 'count' => $count];
    }

    /**
     * 回收站统计
     */
    public function getStats(int $tenantId): array
    {
        $now = now();
        $today = $now->toDateString();

        $query = License::where('tenant_id', $tenantId)->onlyTrashed();

        return [
            'total'       => $query->count(),
            'today'       => (clone $query)->whereDate('deleted_at', $today)->count(),
            'last_7d'     => (clone $query)->where('deleted_at', '>=', $now->copy()->subDays(7))->count(),
            'last_30d'    => (clone $query)->where('deleted_at', '>=', $now->copy()->subDays(30))->count(),
            'expiring_soon' => (clone $query)->where('deleted_at', '<', $now->copy()->subDays(25))->count(),
        ];
    }

    /**
     * 自动清理过期回收站（由定时任务调用）
     */
    public function autoCleanup(): int
    {
        $retentionDays = config('license-lifecycle.trash.retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        $count = License::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();

        if ($count > 0) {
            Log::info("LicenseTrash: auto-cleaned {$count} records older than {$retentionDays} days");
        }

        return $count;
    }
}
