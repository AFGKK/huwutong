<?php

namespace App\Services;

use App\Models\Log;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * M2-130 客户侧审计日志服务
 *
 * 企业客户查看租户内操作记录：
 * - 谁在何时做了什么（激活设备/修改License/邀请成员/改支付方式等）
 * - IP + User-Agent 记录
 * - 可筛选导出
 * - 保留90天
 *
 * 基于现有 AuditService + Log 模型，提供客户门户专用查询封装。
 */
class CustomerAuditLogService
{
    /**
     * 客户关心的操作类别前缀
     */
    const CUSTOMER_ACTION_PREFIXES = [
        'license.',
        'device.',
        'team.',
        'member.',
        'invitation.',
        'payment.',
        'billing.',
        'subscription.',
        'tenant.',
        'user.',
        'security.',
        'api_key.',
        'setting.',
    ];

    /**
     * 获取客户侧审计日志列表（带租户隔离）
     */
    public function getAuditLogs(
        Tenant $tenant,
        array $filters = [],
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Log::query()
            ->with(['user:id,name,email'])
            ->byTenant($tenant->id)
            ->ofType('audit');

        // 按操作前缀筛选
        if (! empty($filters['action_prefix'])) {
            $query->ofActionPrefix($filters['action_prefix']);
        }

        // 按具体操作筛选
        if (! empty($filters['action'])) {
            $query->ofAction($filters['action']);
        }

        // 按用户筛选
        if (! empty($filters['user_id'])) {
            $query->byUser((int) $filters['user_id']);
        }

        // 日期范围
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // 关键词搜索（描述）
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // 排序
        $sortField = $filters['sort'] ?? '-created_at';
        if (str_starts_with($sortField, '-')) {
            $query->orderBy(substr($sortField, 1), 'desc');
        } else {
            $query->orderBy($sortField, 'asc');
        }

        return $query->paginate(min($perPage, 100));
    }

    /**
     * 获取审计日志详情
     */
    public function getAuditLogDetail(Tenant $tenant, int $logId): ?Log
    {
        return Log::where('tenant_id', $tenant->id)
            ->with(['user:id,name,email'])
            ->find($logId);
    }

    /**
     * 获取审计统计概览（客户门户用）
     */
    public function getStats(Tenant $tenant): array
    {
        $baseQuery = Log::where('tenant_id', $tenant->id)
            ->where('type', 'audit');

        $now = now();

        return [
            'total' => (clone $baseQuery)->count(),
            'today' => (clone $baseQuery)->whereDate('created_at', $now->toDateString())->count(),
            'this_week' => (clone $baseQuery)->where('created_at', '>=', $now->startOfWeek()->toDateString())->count(),
            'this_month' => (clone $baseQuery)->where('created_at', '>=', $now->startOfMonth()->toDateString())->count(),
            'top_users' => (clone $baseQuery)
                ->selectRaw('user_id, count(*) as count')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(5)
                ->with('user:id,name,email')
                ->get()
                ->map(fn($log) => [
                    'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'email' => $log->user->email] : null,
                    'count' => $log->count,
                ]),
            'recent_actions' => (clone $baseQuery)
                ->selectRaw('action, count(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'action'),
            'by_date' => (clone $baseQuery)
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->where('created_at', '>=', $now->copy()->subDays(30)->toDateString())
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray(),
        ];
    }

    /**
     * 获取客户可用的操作分类（用于前端筛选下拉）
     */
    public function getActionCategories(): array
    {
        $cat = fn(string $k) => __('app.customer_audit_log.categories.' . $k);

        return [
            'license' => array_merge($cat('license'), ['prefix' => 'license.']),
            'device' => array_merge($cat('device'), ['prefix' => 'device.']),
            'team' => array_merge($cat('team'), ['prefix' => 'team.']),
            'payment' => array_merge($cat('payment'), ['prefix' => 'payment.']),
            'billing' => array_merge($cat('billing'), ['prefix' => 'billing.']),
            'security' => array_merge($cat('security'), ['prefix' => 'security.']),
            'setting' => array_merge($cat('setting'), ['prefix' => 'setting.']),
        ];
    }

    /**
     * 导出审计日志（CSV）
     *
     * @return array{headers: array, rows: \Generator}
     */
    public function exportCsv(Tenant $tenant, array $filters = [], int $maxRows = 10000): array
    {
        $query = Log::query()
            ->with(['user:id,name,email'])
            ->byTenant($tenant->id)
            ->ofType('audit')
            ->orderBy('created_at', 'desc');

        // 应用筛选
        if (! empty($filters['action_prefix'])) {
            $query->ofActionPrefix($filters['action_prefix']);
        }
        if (! empty($filters['action'])) {
            $query->ofAction($filters['action']);
        }
        if (! empty($filters['user_id'])) {
            $query->byUser((int) $filters['user_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $headers = __('app.customer_audit_log.csv_headers');

        $rows = (function () use ($query, $maxRows) {
            $count = 0;
            $query->chunk(500, function ($logs) use (&$count, $maxRows, &$rows) {
                foreach ($logs as $log) {
                    if ($count >= $maxRows) {
                        return false;
                    }
                    $rows[] = [
                        $log->created_at->toDateTimeString(),
                        $log->user?->name ?? '-',
                        $log->user?->email ?? '-',
                        $this->getActionLabel($log->action),
                        $log->description,
                        $log->ip_address ?? '-',
                        $log->user_agent ?? '-',
                    ];
                    $count++;
                }
            });

            yield from ($rows ?? []);
        })();

        return compact('headers', 'rows');
    }

    /**
     * 记录客户侧审计事件（统一入口）
     */
    public function log(
        string $action,
        string $description,
        Tenant $tenant,
        ?User $user = null,
        ?int $licenseId = null,
        ?int $customerId = null,
        ?int $deviceId = null,
        ?array $payload = null,
    ): Log {
        return app(AuditService::class)->log(
            action: $action,
            description: $description,
            tenantId: $tenant->id,
            userId: $user?->id,
            licenseId: $licenseId,
            customerId: $customerId,
            deviceId: $deviceId,
            type: 'audit',
            payload: $payload,
        );
    }

    /**
     * 清理超过保留天数的审计日志
     *
     * @return int 清理数量
     */
    public function prune(int $retentionDays = 90): int
    {
        $cutoff = now()->subDays($retentionDays)->endOfDay();

        return Log::where('type', 'audit')
            ->where('created_at', '<=', $cutoff)
            ->delete();
    }

    /**
     * 获取操作的人类可读标签
     */
    protected function getActionLabel(string $action): string
    {
        $categories = $this->getActionCategories();
        foreach ($categories as $category) {
            if (isset($category['actions'][$action])) {
                return $category['actions'][$action];
            }
        }

        // fallback: infer readable label from action
        $parts = explode('.', $action);
        $label = end($parts);
        $label = str_replace('_', ' ', $label);

        return ucfirst($label);
    }
}
