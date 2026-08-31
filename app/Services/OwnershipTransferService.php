<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\OwnershipTransferRecord;
use App\Models\OwnershipTransferRequest;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OwnershipTransferService
{
    const TRANSFERABLE_TYPES = ['license', 'product'];

    /**
     * 创建所有权转移请求
     */
    public function createRequest(array $data): OwnershipTransferRequest
    {
        $tenantId = $data['tenant_id'] ?? auth()->user()->tenant_id;
        $type = $data['transferable_type'];
        $id = $data['transferable_id'];

        // 验证转移对象存在且属于源客户
        $transferable = $this->resolveTransferable($type, $id, $tenantId);

        // 确定源客户ID
        if ($type === 'license') {
            $sourceCustomerId = $transferable->customer_id;
        } elseif ($type === 'product') {
            // 产品本身不直接关联客户，取产品下第一个License的客户
            $firstLicense = $transferable->licenses()->first();
            $sourceCustomerId = $firstLicense?->customer_id;
            if (!$sourceCustomerId) {
                throw new \RuntimeException(__("app.ownership_transfer.msg_681e589a"));
            }
        } else {
            throw new \RuntimeException(__("app.ownership_transfer.msg_f3bd37ed"));
        }

        if (!$sourceCustomerId) {
            throw new \RuntimeException(__("app.ownership_transfer.msg_f778a360"));
        }

        // 验证目标客户
        $targetCustomer = Customer::findOrFail($data['target_customer_id']);
        if ($targetCustomer->id === $sourceCustomerId) {
            throw new \RuntimeException(__("app.ownership_transfer.msg_af6ebdcb"));
        }

        $data['reference'] = 'OT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        $data['source_customer_id'] = $sourceCustomerId;
        $data['tenant_id'] = $tenantId;
        $data['requested_by'] = auth()->id();
        $data['status'] = 'pending_source';
        $data['source_info'] = $this->buildSourceInfo($type, $transferable, $sourceCustomerId);

        return DB::transaction(function () use ($data) {
            $request = OwnershipTransferRequest::create(array_merge($data, [
                'audit_log' => [
                    ['action' => 'created', 'by' => auth()->id(), 'at' => now()->toIso8601String()],
                ],
            ]));
            return $request->fresh();
        });
    }

    /**
     * 源客户确认
     */
    public function confirmBySource(OwnershipTransferRequest $request): OwnershipTransferRequest
    {
        if ($request->status !== 'pending_source') {
            throw new \RuntimeException(__("app.ownership_transfer.msg_4f86abf2"));
        }

        $request->update([
            'status' => 'pending_target',
            'source_confirmed_by' => auth()->id(),
            'source_confirmed_at' => now(),
            'audit_log' => array_merge($request->audit_log ?? [], [
                ['action' => 'source_confirmed', 'by' => auth()->id(), 'at' => now()->toIso8601String()],
            ]),
        ]);

        return $request->fresh();
    }

    /**
     * 目标客户确认
     */
    public function confirmByTarget(OwnershipTransferRequest $request): OwnershipTransferRequest
    {
        if ($request->status !== 'pending_target') {
            throw new \RuntimeException(__("app.ownership_transfer.msg_f9f98646"));
        }

        $request->update([
            'status' => 'pending_approval',
            'target_confirmed_by' => auth()->id(),
            'target_confirmed_at' => now(),
            'audit_log' => array_merge($request->audit_log ?? [], [
                ['action' => 'target_confirmed', 'by' => auth()->id(), 'at' => now()->toIso8601String()],
            ]),
        ]);

        return $request->fresh();
    }

    /**
     * 管理员审批并执行转移
     */
    public function approveAndExecute(OwnershipTransferRequest $request, ?string $notes = null): OwnershipTransferRequest
    {
        if ($request->status !== 'pending_approval' && $request->status !== 'pending_target') {
            throw new \RuntimeException(__("app.ownership_transfer.msg_bd4f40ef"));
        }

        // 如果只有一方确认，自动完成另一方确认
        if (!$request->source_confirmed_at) {
            $request->update([
                'source_confirmed_by' => auth()->id(),
                'source_confirmed_at' => now(),
            ]);
        }
        if (!$request->target_confirmed_at) {
            $request->update([
                'target_confirmed_by' => auth()->id(),
                'target_confirmed_at' => now(),
            ]);
        }

        return DB::transaction(function () use ($request, $notes) {
            // 执行数据迁移
            $summary = $this->executeMigration($request);

            $request->update([
                'status' => 'completed',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'admin_notes' => $notes ? ($request->admin_notes ? $request->admin_notes . "\n" . $notes : $notes) : $request->admin_notes,
                'migration_summary' => $summary,
                'audit_log' => array_merge($request->audit_log ?? [], [
                    ['action' => 'approved_executed', 'by' => auth()->id(), 'at' => now()->toIso8601String()],
                ]),
            ]);

            return $request->fresh();
        });
    }

    /**
     * 拒绝转移
     */
    public function reject(OwnershipTransferRequest $request, ?string $reason = null): OwnershipTransferRequest
    {
        if (!in_array($request->status, ['pending_source', 'pending_target', 'pending_approval'])) {
            throw new \RuntimeException(__("app.ownership_transfer.msg_dc37dd9b"));
        }

        $request->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
            'audit_log' => array_merge($request->audit_log ?? [], [
                ['action' => 'rejected', 'by' => auth()->id(), 'at' => now()->toIso8601String()],
            ]),
        ]);

        return $request->fresh();
    }

    /**
     * 取消转移
     */
    public function cancel(OwnershipTransferRequest $request): OwnershipTransferRequest
    {
        if (!in_array($request->status, ['pending_source', 'pending_target'])) {
            throw new \RuntimeException(__("app.ownership_transfer.request_not_cancelable_now"));
        }

        $request->update([
            'status' => 'cancelled',
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
            'audit_log' => array_merge($request->audit_log ?? [], [
                ['action' => 'cancelled', 'by' => auth()->id(), 'at' => now()->toIso8601String()],
            ]),
        ]);

        return $request->fresh();
    }

    /**
     * 执行数据迁移
     */
    protected function executeMigration(OwnershipTransferRequest $request): array
    {
        $type = $request->transferable_type;
        $id = $request->transferable_id;
        $targetCustomerId = $request->target_customer_id;
        $records = [];

        try {
            if ($type === 'license') {
                $license = License::findOrFail($id);
                $records = $this->migrateLicense($license, $targetCustomerId, $request->id);
            } elseif ($type === 'product') {
                $product = Product::findOrFail($id);
                $records = $this->migrateProduct($product, $targetCustomerId, $request->id);
            }

            // 记录迁移记录
            foreach ($records as $record) {
                OwnershipTransferRecord::create(array_merge($record, [
                    'transfer_request_id' => $request->id,
                ]));
            }
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'migrated_count' => 0,
            ];
        }

        $summary = [
            'status' => 'completed',
            'migrated_count' => count($records),
            'entity_types' => array_count_values(array_column($records, 'entity_type')),
            'completed_at' => now()->toIso8601String(),
        ];

        return $summary;
    }

    /**
     * 迁移License所有权
     */
    protected function migrateLicense(License $license, int $targetCustomerId, ?int $transferRequestId = null): array
    {
        $records = [];
        $oldCustomerId = $license->customer_id;

        // 1. 迁移License本身
        $license->update(['customer_id' => $targetCustomerId]);
        $records[] = ['entity_type' => 'license', 'entity_id' => $license->id, 'status' => 'migrated', 'notes' => "customer_id: {$oldCustomerId} → {$targetCustomerId}"];

        // 2. 转移关联设备（License下的设备通过license_id关联，不需要改customer_id）

        // 3. 移除自定义字段值（转移到新客户后由目标客户重新配置）
        $cfCount = 0;
        foreach ($license->customFieldValues as $cf) {
            $cf->delete();
            $cfCount++;
        }

        // 4. 记录许可metadata转移历史
        $metadata = $license->metadata ?? [];
        $metadata['ownership_transfers'] = $metadata['ownership_transfers'] ?? [];
        $metadata['ownership_transfers'][] = [
            'from_customer_id' => $oldCustomerId,
            'to_customer_id' => $targetCustomerId,
            'transferred_at' => now()->toIso8601String(),
            'transfer_request_id' => $transferRequestId,
        ];
        $license->update(['metadata' => $metadata]);

        return $records;
    }

    /**
     * 迁移产品所有权（产品本身不绑定客户，迁移产品下的所有License）
     */
    protected function migrateProduct(Product $product, int $targetCustomerId, ?int $transferRequestId = null): array
    {
        $records = [];

        // 产品本身没有customer_id，迁移产品下的所有License
        foreach ($product->licenses as $license) {
            $licenseRecords = $this->migrateLicense($license, $targetCustomerId, $transferRequestId);
            $records = array_merge($records, $licenseRecords);
        }

        return $records;
    }

    // ─── 查询方法 ───

    public function listRequests(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = OwnershipTransferRequest::with([
            'sourceCustomer:id,user_id,tenant_id', 'targetCustomer:id,user_id,tenant_id',
            'requester:id,name', 'approver:id,name', 'transferable',
        ])->where('tenant_id', $tenantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['transferable_type'])) {
            $query->where('transferable_type', $filters['transferable_type']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('reference', 'like', "%{$filters['search']}%")
                  ->orWhereHas('sourceCustomer', fn($cq) => $cq->where('name', 'like', "%{$filters['search']}%"))
                  ->orWhereHas('targetCustomer', fn($cq) => $cq->where('name', 'like', "%{$filters['search']}%"));
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function getRequestDetail(int $id): OwnershipTransferRequest
    {
        return OwnershipTransferRequest::with([
            'sourceCustomer', 'targetCustomer',
            'requester:id,name', 'sourceConfirmer:id,name',
            'targetConfirmer:id,name', 'approver:id,name',
            'transferRecords', 'transferable',
        ])->findOrFail($id);
    }

    public function getStats(int $tenantId): array
    {
        $base = OwnershipTransferRequest::where('tenant_id', $tenantId);

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->whereIn('status', ['pending_source', 'pending_target', 'pending_approval'])->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'total_fees' => (clone $base)->where('status', 'completed')->sum('transfer_fee'),
        ];
    }

    /**
     * 获取可选转移的对象列表
     */
    public function getTransferables(string $type, int $tenantId, ?string $search = null): array
    {
        if ($type === 'license') {
            $query = License::where('tenant_id', $tenantId)
                ->whereIn('status', ['active', 'suspended']);
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('license_key', 'like', "%{$search}%")
                      ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
                });
            }
            return $query->limit(20)->get(['id', 'license_key', 'customer_id', 'status'])->toArray();
        }

        if ($type === 'product') {
            $query = Product::where('is_active', true);
            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }
            return $query->limit(20)->get(['id', 'name'])->toArray();
        }

        return [];
    }

    /**
     * 搜索客户
     */
    public function searchCustomers(int $tenantId, string $search): array
    {
        return Customer::where('tenant_id', $tenantId)
            ->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%"));
            })
            ->with('user:id,name,email')
            ->limit(20)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->user?->name ?? "Customer #{$c->id}",
                'email' => $c->user?->email ?? '',
            ])
            ->toArray();
    }

    // ─── 内部方法 ───

    protected function resolveTransferable(string $type, int $id, int $tenantId): License|Product
    {
        return match ($type) {
            'license' => License::where('tenant_id', $tenantId)->findOrFail($id),
            'product' => Product::findOrFail($id),
default => throw new \RuntimeException(__("app.ownership_transfer.unsupported_transfer_type", ['type' => $type])),
        };
    }

    protected function buildSourceInfo(string $type, License|Product $transferable, int $sourceCustomerId): array
    {
        $info = [
            'transferable_type' => $type,
            'transferable_id' => $transferable->id,
            'source_customer_id' => $sourceCustomerId,
        ];

        if ($type === 'license') {
            $info['license_key'] = $transferable->license_key;
            $info['license_status'] = $transferable->status;
            $info['devices_count'] = $transferable->devices()->count();
            $info['has_subscription'] = $transferable->subscription_id !== null;
        } elseif ($type === 'product') {
            $info['product_name'] = $transferable->name;
            $info['licenses_count'] = $transferable->licenses()->count();
        }

        return $info;
    }
}
