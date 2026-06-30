<?php

namespace App\Services;

use App\Models\Device;
use App\Models\License;
use App\Models\LicenseTransferRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferService
{
    // ═══════════════ 转移请求管理 ═══════════════

    public function listRequests(array $filters = [], int $perPage = 20)
    {
        $query = LicenseTransferRequest::with(['license', 'requester:id,name', 'approver:id,name', 'targetCustomer:id,name', 'targetDevice:id,fingerprint'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['license_id'])) $query->where('license_id', $filters['license_id']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('reference', 'like', "%{$filters['search']}%")
                  ->orWhereHas('license', fn($lq) => $lq->where('license_key', 'like', "%{$filters['search']}%"));
            });
        }

        return $query->paginate($perPage);
    }

    public function createRequest(array $data): LicenseTransferRequest
    {
        $license = License::findOrFail($data['license_id']);

        // Check license is transferable
        if (!in_array($license->status, ['active', 'suspended'])) {
            throw new \RuntimeException('License 当前状态不可转移');
        }

        $data['reference'] = 'TX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        $data['requested_by'] = auth()->id();
        $data['request_ip'] = request()->ip();
        $data['source_info'] = [
            'customer_id' => $license->customer_id,
            'customer_name' => $license->customer?->name,
            'license_key' => $license->license_key,
            'license_status' => $license->status,
            'devices_count' => $license->devices()->count(),
            'device_ids' => $license->devices()->pluck('id')->toArray(),
        ];

        return DB::transaction(function () use ($data) {
            $request = LicenseTransferRequest::create($data);

            // 记录初始审计日志
            $request->update([
                'audit_log' => [
                    ['action' => 'created', 'by' => auth()->id(), 'at' => now()->toIso8601String()],
                ],
            ]);

            return $request->fresh();
        });
    }

    public function approveRequest(LicenseTransferRequest $request, ?string $notes = null): LicenseTransferRequest
    {
        if (!$request->isProcessable()) {
            throw new \RuntimeException('该请求当前无法处理');
        }

        $license = $request->license;

        return DB::transaction(function () use ($request, $license, $notes) {
            $audit = $request->audit_log ?? [];
            $audit[] = ['action' => 'approved', 'by' => auth()->id(), 'at' => now()->toIso8601String()];

            $request->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'admin_notes' => $notes,
                'audit_log' => $audit,
            ]);

            // 执行实际转移
            $this->executeTransfer($request, $license);

            return $request->fresh();
        });
    }

    public function rejectRequest(LicenseTransferRequest $request, string $reason): LicenseTransferRequest
    {
        if (!$request->isProcessable()) {
            throw new \RuntimeException('该请求当前无法处理');
        }

        $audit = $request->audit_log ?? [];
        $audit[] = ['action' => 'rejected', 'by' => auth()->id(), 'at' => now()->toIso8601String()];

        $request->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'admin_notes' => $reason,
            'audit_log' => $audit,
        ]);

        return $request->fresh();
    }

    public function cancelRequest(LicenseTransferRequest $request): LicenseTransferRequest
    {
        if (!in_array($request->status, ['pending'])) {
            throw new \RuntimeException('当前状态不可取消');
        }

        $request->update([
            'status' => 'cancelled',
            'cancelled_by' => auth()->id(),
        ]);

        return $request->fresh();
    }

    protected function executeTransfer(LicenseTransferRequest $request, License $license): void
    {
        $type = $request->type;

        $auditEntry = ['action' => 'transferred', 'type' => $type, 'at' => now()->toIso8601String()];

        switch ($type) {
            case 'device_transfer':
                // 设备间转移：停用旧设备，如果指定目标设备则激活
                $deviceIds = $request->source_info['device_ids'] ?? [];
                if (!empty($deviceIds)) {
                    Device::whereIn('id', $deviceIds)
                        ->update(['status' => 'transferred']);
                }
                if ($request->target_device_fingerprint) {
                    // 如果目标设备已存在，更新；否则记录到日志（不自动创建设备，因需要 tenant_id）
                    $existing = Device::where('fingerprint', $request->target_device_fingerprint)->first();
                    if ($existing) {
                        $existing->update(['status' => 'active']);
                    }
                }
                $auditEntry['details'] = 'device transfer completed';
                break;

            case 'customer_transfer':
                // 客户间转移：变更 license 的 customer_id
                $license->update(['customer_id' => $request->target_customer_id]);
                $auditEntry['details'] = 'customer changed to #' . $request->target_customer_id;
                break;

            case 'user_transfer':
                // 用户间转移：关联到新用户（通过 customer）
                if ($request->target_user_id) {
                    $targetUser = User::find($request->target_user_id);
                    if ($targetUser && $targetUser->customer_id) {
                        $license->update(['customer_id' => $targetUser->customer_id]);
                        $auditEntry['details'] = 'user transferred to #' . $request->target_user_id;
                    }
                }
                break;
        }

        // 标记转移完成
        $request->update(['status' => 'completed']);

        // 记录到 license metadata
        $meta = $license->metadata ?? [];
        $meta['transfer_history'][] = $auditEntry;
        $license->update(['metadata' => $meta]);
    }

    // ═══════════════ 统计和用户视图 ═══════════════

    public function getStats(): array
    {
        return [
            'total' => LicenseTransferRequest::count(),
            'pending' => LicenseTransferRequest::where('status', 'pending')->count(),
            'approved' => LicenseTransferRequest::where('status', 'approved')->count(),
            'completed' => LicenseTransferRequest::where('status', 'completed')->count(),
            'rejected' => LicenseTransferRequest::where('status', 'rejected')->count(),
            'cancelled' => LicenseTransferRequest::where('status', 'cancelled')->count(),
            'by_type' => LicenseTransferRequest::selectRaw('type, count(*) as count')
                ->groupBy('type')->pluck('count', 'type'),
        ];
    }

    public function myRequests(User $user, array $filters = [], int $perPage = 20)
    {
        $query = LicenseTransferRequest::with(['license', 'targetCustomer:id,name', 'targetDevice:id,name'])
            ->where('requested_by', $user->id)
            ->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);

        return $query->paginate($perPage);
    }

    public function getTransferableLicenses(User $user)
    {
        $customerId = $user->customer?->id;
        if (!$customerId) return collect();

        return License::where('customer_id', $customerId)
            ->whereIn('status', ['active', 'suspended'])
            ->with(['devices'])
            ->get(['id', 'license_key', 'product_name', 'type', 'status', 'max_devices', 'seats']);
    }

    // ═══════════════ 增强功能 (M3-08) ═══════════════

    /**
     * 生成设备转移验证码（6位数字，5分钟有效期）
     */
    public function generateVerificationCode(LicenseTransferRequest $request): string
    {
        if ($request->type !== 'device_transfer') {
            throw new \RuntimeException('仅设备转移需要验证码');
        }

        $code = (string) random_int(100000, 999999);
        $cacheKey = 'transfer_verify_' . $request->id;

        Cache::put($cacheKey, $code, 300); // 5分钟

        $request->update([
            'verification_token' => $code,
            'verification_expires_at' => now()->addMinutes(5),
        ]);

        return $code;
    }

    /**
     * 验证设备转移验证码
     */
    public function verifyCode(LicenseTransferRequest $request, string $code): bool
    {
        $cacheKey = 'transfer_verify_' . $request->id;
        $cached = Cache::get($cacheKey);

        if (!$cached || $cached !== $code) {
            return false;
        }

        // 验证通过，清除缓存
        Cache::forget($cacheKey);
        return true;
    }

    /**
     * 按租户范围获取转移请求列表
     */
    public function listRequestsByTenant(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = LicenseTransferRequest::with(['license', 'requester:id,name', 'approver:id,name', 'targetCustomer:id,name', 'targetDevice:id,fingerprint'])
            ->whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('reference', 'like', "%{$filters['search']}%")
                  ->orWhereHas('license', fn($lq) => $lq->where('license_key', 'like', "%{$filters['search']}%"));
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * 按租户统计
     */
    public function getStatsByTenant(int $tenantId): array
    {
        return [
            'total' => LicenseTransferRequest::whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))->count(),
            'pending' => LicenseTransferRequest::whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))->where('status', 'pending')->count(),
            'approved' => LicenseTransferRequest::whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))->where('status', 'approved')->count(),
            'completed' => LicenseTransferRequest::whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))->where('status', 'completed')->count(),
            'rejected' => LicenseTransferRequest::whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))->where('status', 'rejected')->count(),
            'cancelled' => LicenseTransferRequest::whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))->where('status', 'cancelled')->count(),
            'by_type' => LicenseTransferRequest::whereHas('license', fn($q) => $q->where('tenant_id', $tenantId))
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')->pluck('count', 'type'),
        ];
    }
}
