<?php

namespace App\Services;

use App\Models\EnterpriseContract;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 企业合同管理服务 (M3-21)
 *
 * 管理企业合同的完整生命周期：
 * 1. 合同 CRUD（含审批流程）
 * 2. 合同到期提醒
 * 3. 合同续签
 * 4. 合同仪表盘
 */
class EnterpriseContractService
{
    /**
     * 列示合同
     */
    public function listContracts(int $tenantId, array $filters = []): array
    {
        $query = EnterpriseContract::with(['customer', 'creator:id,name', 'approver:id,name'])
            ->whereHas('customer', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('contract_number', 'like', "%{$s}%");
            });
        }
        // 即将到期
        if (!empty($filters['expiring_within_days'])) {
            $days = (int) $filters['expiring_within_days'];
            $query->where('status', 'active')
                ->whereDate('end_date', '>=', now())
                ->whereDate('end_date', '<=', now()->addDays($days));
        }
        // 已经过期
        if (!empty($filters['overdue'])) {
            $query->where('status', 'active')
                ->whereDate('end_date', '<', now());
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage)->withQueryString()->through(function ($c) {
            return [
                'id' => $c->id,
                'contract_number' => $c->contract_number,
                'name' => $c->name,
                'customer_name' => $c->customer ? "#{$c->customer->id}" : '已删除',
                'customer_id' => $c->customer_id,
                'status' => $c->status,
                'approval_status' => $c->approval_status,
                'total_value' => (float) $c->total_value,
                'negotiated_amount' => (float) $c->negotiated_amount,
                'discount_rate' => (float) $c->discount_rate,
                'currency' => $c->currency,
                'start_date' => $c->start_date?->toDateString(),
                'end_date' => $c->end_date?->toDateString(),
                'billing_cycle_days' => $c->billing_cycle_days,
                'auto_renew' => $c->auto_renew,
                'renewal_notice_days' => $c->renewal_notice_days,
                'created_by_name' => $c->creator?->name,
                'approved_by_name' => $c->approver?->name,
                'is_active' => $c->isActive(),
                'days_remaining' => $c->end_date ? now()->startOfDay()->diffInDays($c->end_date, false) : null,
                'created_at' => $c->created_at?->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * 创建合同
     */
    public function createContract(int $tenantId, int $userId, array $data): EnterpriseContract
    {
        // 自动生成合同编号
        $contractNumber = $data['contract_number'] ?? $this->generateContractNumber($tenantId);

        return EnterpriseContract::create([
            'contract_number' => $contractNumber,
            'name' => $data['name'],
            'customer_id' => $data['customer_id'],
            'status' => 'draft',
            'total_value' => $data['total_value'] ?? 0,
            'currency' => $data['currency'] ?? 'CNY',
            'discount_rate' => $data['discount_rate'] ?? 0,
            'negotiated_amount' => $data['negotiated_amount'] ?? 0,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'billing_cycle_days' => $data['billing_cycle_days'] ?? 30,
            'licensed_items' => $data['licensed_items'] ?? '[]',
            'terms' => $data['terms'] ?? null,
            'special_terms' => $data['special_terms'] ?? null,
            'auto_renew' => $data['auto_renew'] ?? false,
            'renewal_notice_days' => $data['renewal_notice_days'] ?? 30,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ]);
    }

    /**
     * 更新合同
     */
    public function updateContract(int $tenantId, int $contractId, array $data): EnterpriseContract
    {
        $contract = $this->findContract($tenantId, $contractId);

        $allowedFields = ['name', 'total_value', 'currency', 'discount_rate', 'negotiated_amount',
            'start_date', 'end_date', 'billing_cycle_days',
            'licensed_items', 'terms', 'special_terms',
            'auto_renew', 'renewal_notice_days', 'notes'];

        $contract->update(array_intersect_key($data, array_flip($allowedFields)));
        return $contract;
    }

    /**
     * 删除合同
     */
    public function deleteContract(int $tenantId, int $contractId): void
    {
        $contract = $this->findContract($tenantId, $contractId);
        $contract->delete();
    }

    // ─── 审批流程 ───

    /**
     * 提交审批
     */
    public function submitForApproval(int $tenantId, int $contractId): EnterpriseContract
    {
        $contract = $this->findContract($tenantId, $contractId);
        if ($contract->status !== 'draft') {
            throw new \RuntimeException('只有草稿状态可以提交审批');
        }
        $contract->update(['status' => 'pending_approval', 'approval_status' => 'pending']);
        return $contract;
    }

    /**
     * 审批合同
     */
    public function approveContract(int $tenantId, int $contractId, int $approverId, string $action, ?string $notes = null): EnterpriseContract
    {
        $contract = $this->findContract($tenantId, $contractId);

        if ($contract->approval_status !== 'pending') {
            throw new \RuntimeException('合同不在待审批状态');
        }

        $data = [
            'approval_status' => $action,
            'approval_notes' => $notes,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ];

        if ($action === 'approved') {
            $data['status'] = 'active';
        } elseif ($action === 'rejected') {
            $data['status'] = 'draft';
        }

        $contract->update($data);
        return $contract;
    }

    // ─── 合同操作 ───

    /**
     * 终止合同
     */
    public function terminateContract(int $tenantId, int $contractId): EnterpriseContract
    {
        $contract = $this->findContract($tenantId, $contractId);
        if ($contract->status !== 'active') {
            throw new \RuntimeException('只有活跃合同可以终止');
        }
        $contract->update(['status' => 'terminated', 'end_date' => now()->toDateString()]);
        return $contract;
    }

    /**
     * 执行续签
     */
    public function renewContract(int $tenantId, int $contractId, ?array $newData = null): EnterpriseContract
    {
        $contract = $this->findContract($tenantId, $contractId);

        if (!$contract->auto_renew) {
            throw new \RuntimeException('该合同未启用自动续签');
        }

        // 创建续签合同
        $renewal = $this->createContract($tenantId, $contract->created_by ?? 1, [
            'name' => $contract->name . ' (续签)',
            'customer_id' => $contract->customer_id,
            'start_date' => $contract->end_date->copy()->addDay()->toDateString(),
            'end_date' => $contract->end_date->copy()->addYear()->toDateString(),
            'total_value' => $contract->total_value,
            'negotiated_amount' => $contract->negotiated_amount,
            'currency' => $contract->currency,
            'billing_cycle_days' => $contract->billing_cycle_days,
            'auto_renew' => $contract->auto_renew,
            'renewal_notice_days' => $contract->renewal_notice_days,
            'terms' => $newData['terms'] ?? $contract->terms,
            'licensed_items' => $newData['licensed_items'] ?? $contract->licensed_items,
        ]);

        // 标记原合同已续签
        $contract->update([
            'renewed_contract_id' => $renewal->id,
            'status' => 'active',
        ]);

        return $renewal;
    }

    // ─── 仪表盘 ───

    /**
     * 获取合同仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $now = now()->startOfDay();

        // 状态分布
        $statusDistribution = EnterpriseContract::whereHas('customer', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->selectRaw("status, COUNT(*) as total")
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();

        // 审批状态分布
        $approvalDistribution = EnterpriseContract::whereHas('customer', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->where('approval_status', '!=', 'approved')
            ->selectRaw("approval_status, COUNT(*) as total")
            ->groupBy('approval_status')
            ->get()
            ->pluck('total', 'approval_status')
            ->toArray();

        // 即将到期（30天内）
        $expiringSoon = EnterpriseContract::whereHas('customer', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->where('status', 'active')
            ->whereDate('end_date', '>=', $now)
            ->whereDate('end_date', '<=', $now->copy()->addDays(30))
            ->count();

        // 已过期
        $overdue = EnterpriseContract::whereHas('customer', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->where('status', 'active')
            ->whereDate('end_date', '<', $now)
            ->count();

        // 待审批
        $pendingApproval = EnterpriseContract::whereHas('customer', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->where('approval_status', 'pending')
            ->count();

        // 合同总值
        $totalValue = EnterpriseContract::whereHas('customer', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->where('status', 'active')
            ->sum('total_value');

        $negotiatedValue = EnterpriseContract::whereHas('customer', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->where('status', 'active')
            ->sum('negotiated_amount');

        return [
            'total_contracts' => array_sum($statusDistribution),
            'active_contracts' => $statusDistribution['active'] ?? 0,
            'draft_contracts' => $statusDistribution['draft'] ?? 0,
            'expired_contracts' => $statusDistribution['expired'] ?? 0,
            'terminated_contracts' => $statusDistribution['terminated'] ?? 0,
            'pending_approval' => $pendingApproval,
            'expiring_soon' => $expiringSoon,
            'overdue_contracts' => $overdue,
            'total_value' => round((float) $totalValue, 2),
            'negotiated_value' => round((float) $negotiatedValue, 2),
            'status_distribution' => $statusDistribution,
            'approval_distribution' => $approvalDistribution,
        ];
    }

    /**
     * 获取即将到期的合同
     */
    public function getExpiringContracts(int $tenantId, int $withinDays = 30): array
    {
        return EnterpriseContract::with('customer')
            ->whereHas('customer', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->where('status', 'active')
            ->whereDate('end_date', '>=', now())
            ->whereDate('end_date', '<=', now()->addDays($withinDays))
            ->orderBy('end_date')
            ->get()
            ->map(function ($c) {
                $daysRemaining = now()->startOfDay()->diffInDays($c->end_date, false);
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'contract_number' => $c->contract_number,
                    'customer_name' => $c->customer ? "#{$c->customer->id}" : '已删除',
                    'end_date' => $c->end_date?->toDateString(),
                    'days_remaining' => $daysRemaining,
                    'total_value' => (float) $c->total_value,
                    'auto_renew' => $c->auto_renew,
                ];
            })
            ->toArray();
    }

    // ─── 辅助方法 ───

    protected function findContract(int $tenantId, int $contractId): EnterpriseContract
    {
        $contract = EnterpriseContract::with('customer')
            ->where('id', $contractId)
            ->firstOrFail();

        // 验证租户归属
        if ($contract->customer && $contract->customer->tenant_id !== $tenantId) {
            throw new \RuntimeException('无权访问此合同');
        }

        return $contract;
    }

    protected function generateContractNumber(int $tenantId): string
    {
        $prefix = 'CT-' . str_pad($tenantId, 4, '0', STR_PAD_LEFT);
        $date = now()->format('Ymd');
        $seq = EnterpriseContract::where('contract_number', 'like', "{$prefix}-{$date}-%")->count() + 1;
        return "{$prefix}-{$date}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
