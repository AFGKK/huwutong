<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseChangeApproval;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * License 变更审批工作流服务 (M2-11)
 *
 * 管理 License 升级/降级/转移/改席位/改类型 的审批流程。
 * 支持：创建审批请求、审批/拒绝、自动过期、通知。
 *
 * @depends M2-11 config
 */
class ApprovalWorkflowService
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    /**
     * 创建变更审批请求
     *
     * @param License $license
     * @param string $action upgrade/downgrade/transfer/seat_change/type_change
     * @param array $requestData 变更请求参数
     * @param User $requester
     * @param string|null $reason
     * @return LicenseChangeApproval
     */
    public function createRequest(License $license, string $action, array $requestData, User $requester, ?string $reason = null): LicenseChangeApproval
    {
        $expireHours = config('license-lifecycle.approval.expire_hours', 72);

        $approval = LicenseChangeApproval::create([
            'tenant_id'        => $license->tenant_id,
            'license_id'       => $license->id,
            'action'           => $action,
            'status'           => 'pending',
            'request_data'     => $requestData,
            'current_snapshot' => $this->snapshotLicense($license),
            'reason'           => $reason,
            'requested_by'     => $requester->id,
            'expires_at'       => now()->addHours($expireHours),
        ]);

        $this->notifyApprovalCreated($approval);

        return $approval;
    }

    /**
     * 审批通过
     */
    public function approve(LicenseChangeApproval $approval, User $approver): bool
    {
        if ($approval->status !== 'pending') {
            return false;
        }

        if ($approval->isExpired()) {
            $approval->update(['status' => 'expired']);
            return false;
        }

        $approval->update([
            'status'      => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->notifyApprovalUpdated($approval);

        return true;
    }

    /**
     * 拒绝审批
     */
    public function reject(LicenseChangeApproval $approval, User $approver, string $reason): bool
    {
        if ($approval->status !== 'pending') {
            return false;
        }

        $approval->update([
            'status'        => 'rejected',
            'approved_by'   => $approver->id,
            'reject_reason' => $reason,
        ]);

        $this->notifyApprovalUpdated($approval);

        return true;
    }

    /**
     * 取消审批（申请人撤回）
     */
    public function cancel(LicenseChangeApproval $approval, User $canceller): bool
    {
        if ($approval->status !== 'pending') {
            return false;
        }

        if ($approval->requested_by !== $canceller->id && !$canceller->hasRole('admin')) {
            return false;
        }

        $approval->update(['status' => 'cancelled']);

        return true;
    }

    /**
     * 审批列表
     */
    public function getApprovals(int $tenantId, array $filters = []): array
    {
        $query = LicenseChangeApproval::byTenant($tenantId)->with(['license', 'requester', 'approver']);

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['license_id'])) {
            $query->where('license_id', $filters['license_id']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->latest()->forPage($page, $perPage)->get();

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 仪表盘统计
     */
    public function getDashboard(int $tenantId): array
    {
        $today = now()->toDateString();

        return [
            'pending'  => LicenseChangeApproval::byTenant($tenantId)->pending()->count(),
            'approved' => LicenseChangeApproval::byTenant($tenantId)->byStatus('approved')->count(),
            'rejected' => LicenseChangeApproval::byTenant($tenantId)->byStatus('rejected')->count(),
            'today'    => LicenseChangeApproval::byTenant($tenantId)->whereDate('created_at', $today)->count(),
            'expired'  => LicenseChangeApproval::byTenant($tenantId)->byStatus('expired')->count(),
        ];
    }

    /**
     * 处理超时审批（由定时任务调用）
     */
    public function processExpired(): int
    {
        $count = 0;
        LicenseChangeApproval::pending()
            ->where('expires_at', '<', now())
            ->chunk(100, function ($approvals) use (&$count) {
                foreach ($approvals as $approval) {
                    $approval->update(['status' => 'expired']);
                    $count++;
                }
            });
        return $count;
    }

    /**
     * 检查某操作是否需要审批
     */
    public function requiresApproval(string $action): bool
    {
        $config = config('license-lifecycle.approval.require_approval', []);
        return $config[$action] ?? false;
    }

    /**
     * 快照 License 当前数据
     */
    protected function snapshotLicense(License $license): array
    {
        return $license->only([
            'id', 'license_key', 'status', 'type', 'product_id', 'customer_id',
            'max_devices', 'seats', 'expires_at', 'metadata',
            'allowed_domains', 'allowed_ips', 'allowed_versions',
            'feature_flags', 'is_trial', 'trial_ends_at',
        ]);
    }

    /**
     * 通知审批创建
     */
    protected function notifyApprovalCreated(LicenseChangeApproval $approval): void
    {
        // 通知所有管理员
        $admins = User::role('admin')->where('tenant_id', $approval->tenant_id)->get();
        foreach ($admins as $admin) {
            $this->notificationService->send(
                $admin->id,
                'approval',
                "License 变更审批请求 #{$approval->id}",
                "License {$approval->license->license_key} 申请 {$approval->action}，请审批。",
                ['approval_id' => $approval->id, 'action' => $approval->action],
                $approval->tenant_id,
            );
        }
    }

    /**
     * 通知审批状态更新
     */
    protected function notifyApprovalUpdated(LicenseChangeApproval $approval): void
    {
        $this->notificationService->send(
            $approval->requested_by,
            'approval',
            "审批 {$approval->status}",
            "您的 License {$approval->license->license_key} 的 {$approval->action} 请求已" . match($approval->status) {
                'approved' => '通过',
                'rejected' => '被拒绝：' . ($approval->reject_reason ?? '无理由'),
                default => $approval->status,
            },
            ['approval_id' => $approval->id, 'status' => $approval->status],
            $approval->tenant_id,
        );
    }
}
