<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Device;
use App\Models\License;
use App\Models\LicenseMergeJob;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * License 继承/合并服务
 *
 * 企业收购场景：将源客户的 License 批量迁移到目标客户，
 * 同时处理设备迁移、过期设置、审计链记录。
 *
 * 合并策略：
 * - Active/Suspended License → 迁移到目标客户，保留原状态
 * - Expired/Revoked/Refunded → 跳过，记录到审计
 * - Blacklisted/Pending → 跳过
 * - 所有关联设备 → 迁移到目标客户
 * - 每个被迁移的 License 的 metadata 添加合并审计记录
 * - 源客户标记编号标识已被合并
 */
class LicenseMergeService
{
    /**
     * 预览合并影响（仅查询，不修改数据）
     */
    public function previewMerge(Customer $source, Customer $target): array
    {
        $this->validateMerge($source, $target);

        $licenses = $source->licenses()->withCount('devices')->get();

        $grouped = [
            'migratable' => [],  // active/suspended - 可迁移
            'retirable' => [],   // expired/revoked/refunded - 可设置过期合并标记
            'skippable' => [],   // blacklisted/pending - 跳过
        ];

        foreach ($licenses as $license) {
            $entry = [
                'id' => $license->id,
                'license_key' => $license->license_key,
                'type' => $license->type,
                'status' => $license->status,
                'product_id' => $license->product_id,
                'seats' => $license->seats,
                'max_devices' => $license->max_devices,
                'devices_count' => $license->devices_count,
                'expires_at' => $license->expires_at?->toIso8601String(),
            ];

            switch ($license->status) {
                case 'active':
                case 'suspended':
                    $grouped['migratable'][] = $entry;
                    break;
                case 'expired':
                case 'revoked':
                case 'refunded':
                    $grouped['retirable'][] = $entry;
                    break;
                default: // pending, blacklisted, frozen
                    $grouped['skippable'][] = $entry;
            }
        }

        $totalDevices = Device::whereIn('license_id', $licenses->pluck('id'))->count();

        return [
            'source' => [
                'id' => $source->id,
                'name' => $source->user?->name ?? '客户#' . $source->id,
                'total_licenses' => $licenses->count(),
            ],
            'target' => [
                'id' => $target->id,
                'name' => $target->user?->name ?? '客户#' . $target->id,
            ],
            'summary' => [
                'to_migrate' => count($grouped['migratable']),
                'to_retire' => count($grouped['retirable']),
                'to_skip' => count($grouped['skippable']),
                'devices_to_migrate' => $totalDevices,
            ],
            'licenses' => $grouped,
        ];
    }

    /**
     * 执行 License 合并
     */
    public function merge(Customer $source, Customer $target, ?int $mergedByUserId = null, array $options = []): LicenseMergeJob
    {
        $this->validateMerge($source, $target);

        // 如果源客户已被其他合并引用过，只需记录跳过
        $isAlreadyMerged = $source->status === 'merged' || $source->merged_into_customer_id;

        return DB::transaction(function () use ($source, $target, $mergedByUserId, $options, $isAlreadyMerged) {
            $source->loadCount('licenses');

            // 创建合并任务记录
            $job = LicenseMergeJob::create([
                'tenant_id' => $source->tenant_id,
                'source_customer_id' => $source->id,
                'target_customer_id' => $target->id,
                'status' => 'pending',
                'total_licenses' => $isAlreadyMerged ? 0 : $source->licenses_count,
                'total_devices' => $isAlreadyMerged ? 0 : Device::whereIn('license_id', $source->licenses()->pluck('id'))->count(),
                'merged_by' => $mergedByUserId,
                'notes' => $options['notes'] ?? null,
                'conflict_resolution' => $options['conflict_resolution'] ?? [],
                'merge_audit' => [
                    [
                        'action' => 'initiated',
                        'by' => $mergedByUserId,
                        'at' => now()->toIso8601String(),
                        'details' => "Merging licenses from Customer#{$source->id} to Customer#{$target->id}",
                    ],
                ],
            ]);

            if ($isAlreadyMerged) {
                $job->update([
                    'status' => 'completed',
                    'summary' => [
                        'source_already_merged' => true,
                        'message' => '源客户已经是合并状态，无需处理',
                    ],
                    'merged_at' => now(),
                ]);
                return $job->fresh();
            }

            $errors = [];
            $mergedLicenses = 0;
            $skippedLicenses = 0;
            $failedLicenses = 0;
            $migratedDevices = 0;
            $mergeAudit = $job->merge_audit ?? [];

            try {
                $licenses = $source->licenses()->withCount('devices')->get();
                $now = now()->toIso8601String();

                foreach ($licenses as $license) {
                    // 根据状态决定处理方式
                    switch ($license->status) {
                        case 'active':
                        case 'suspended':
                            // 迁移 License 到目标客户
                            $oldCustomerId = $license->customer_id;

                            // 获取迁移前的设备信息
                            $deviceKeys = $license->devices()->pluck('fingerprint')->toArray();

                            // 变更 customer_id
                            $license->update(['customer_id' => $target->id]);

                            // 迁移设备
                            Device::where('license_id', $license->id)
                                ->update(['tenant_id' => $target->tenant_id]);

                            $migratedDevices += count($deviceKeys);

                            // 记录合并审计到 license metadata
                            $meta = $license->metadata ?? [];
                            $meta['merge_history'] = $meta['merge_history'] ?? [];
                            $meta['merge_history'][] = [
                                'action' => 'merged',
                                'from_customer_id' => $oldCustomerId,
                                'to_customer_id' => $target->id,
                                'merge_job_id' => $job->id,
                                'merged_by' => $mergedByUserId,
                                'merged_at' => $now,
                                'devices_transferred' => count($deviceKeys),
                            ];
                            $license->update(['metadata' => $meta]);

                            $mergedLicenses++;

                            $mergeAudit[] = [
                                'action' => 'license_merged',
                                'license_id' => $license->id,
                                'license_key' => $license->license_key,
                                'status' => $license->status,
                                'from_customer' => $oldCustomerId,
                                'to_customer' => $target->id,
                                'devices_transferred' => count($deviceKeys),
                                'at' => $now,
                            ];
                            break;

                        case 'expired':
                        case 'revoked':
                        case 'refunded':
                            // 对于过期/撤销的 License，记录合并审计但不迁移
                            $meta = $license->metadata ?? [];
                            $meta['merge_history'] = $meta['merge_history'] ?? [];
                            $meta['merge_history'][] = [
                                'action' => 'skipped_in_merge',
                                'reason' => "License is {$license->status}",
                                'merge_job_id' => $job->id,
                                'merged_at' => $now,
                            ];
                            $license->update(['metadata' => $meta]);

                            $skippedLicenses++;
                            $mergeAudit[] = [
                                'action' => 'license_skipped',
                                'license_id' => $license->id,
                                'license_key' => $license->license_key,
                                'status' => $license->status,
                                'reason' => "Status {$license->status} not migratable",
                                'at' => $now,
                            ];
                            break;

                        default:
                            // pending, blacklisted, frozen - 完全跳过
                            $skippedLicenses++;
                            $mergeAudit[] = [
                                'action' => 'license_skipped',
                                'license_id' => $license->id,
                                'license_key' => $license->license_key,
                                'status' => $license->status,
                                'reason' => "Non-migratable status: {$license->status}",
                                'at' => $now,
                            ];
                    }
                }

                // 标记源客户
                $source->update([
                    'merged_into_customer_id' => $target->id,
                ]);
                $target->increment('merge_count');

                $mergeAudit[] = [
                    'action' => 'completed',
                    'merged_licenses' => $mergedLicenses,
                    'skipped_licenses' => $skippedLicenses,
                    'failed_licenses' => $failedLicenses,
                    'devices_migrated' => $migratedDevices,
                    'at' => $now,
                ];

                $summary = [
                    'licenses_moved' => $mergedLicenses,
                    'licenses_skipped' => $skippedLicenses,
                    'licenses_failed' => $failedLicenses,
                    'devices_migrated' => $migratedDevices,
                    'source_marked' => true,
                ];

                $job->update([
                    'status' => 'completed',
                    'merged_licenses' => $mergedLicenses,
                    'skipped_licenses' => $skippedLicenses,
                    'failed_licenses' => $failedLicenses,
                    'migrated_devices' => $migratedDevices,
                    'summary' => $summary,
                    'merge_audit' => $mergeAudit,
                    'merged_at' => now(),
                ]);

            } catch (Exception $e) {
                $errors[] = $e->getMessage();

                $mergeAudit[] = [
                    'action' => 'failed',
                    'error' => $e->getMessage(),
                    'at' => now()->toIso8601String(),
                ];

                $job->update([
                    'status' => 'failed',
                    'errors' => $errors,
                    'merge_audit' => $mergeAudit,
                ]);

                Log::error('LicenseMerge failed', [
                    'job_id' => $job->id,
                    'source_id' => $source->id,
                    'target_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            return $job->fresh();
        });
    }

    /**
     * 验证合并前提条件
     */
    protected function validateMerge(Customer $source, Customer $target): void
    {
        if ($source->id === $target->id) {
            throw new Exception(__("app.license_merge.cannot_merge_license_to_self"));
        }

        if ($source->tenant_id !== $target->tenant_id) {
            throw new Exception(__("app.license_merge.can_only_merge_same_tenant_customers"));
        }
    }

    /**
     * 获取合并历史
     */
    public function getMergeHistory(int $tenantId, int $perPage = 20)
    {
        return LicenseMergeJob::where('tenant_id', $tenantId)
            ->with(['sourceCustomer', 'targetCustomer', 'mergedBy'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * 获取合并详情
     */
    public function getMergeDetail(LicenseMergeJob $job): LicenseMergeJob
    {
        return $job->load(['sourceCustomer', 'targetCustomer', 'mergedBy']);
    }

    /**
     * 搜索客户（用于前端选择器）
     */
    public function searchCustomers(int $tenantId, string $keyword)
    {
        return Customer::where('tenant_id', $tenantId)
            ->where(function ($q) use ($keyword) {
                $q->where('id', $keyword)
                  ->orWhereHas('user', function ($q) use ($keyword) {
                      $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                  });
            })
            ->with('user:id,name,email')
            ->limit(20)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'user_name' => $c->user?->name ?? '（无用户）',
                'user_email' => $c->user?->email ?? '-',
                'type' => $c->type,
                'level' => $c->level,
                'status' => $c->status,
                'license_count' => $c->licenses()->count(),
            ]);
    }

    /**
     * 回滚合并（将 License 和设备恢复到源客户）
     */
    public function rollback(LicenseMergeJob $job): LicenseMergeJob
    {
        if ($job->status !== 'completed') {
            throw new Exception(__("app.license_merge.can_only_rollback_completed_merge"));
        }

        $source = $job->sourceCustomer;
        if (!$source) {
            throw new Exception(__("app.license_merge.source_customer_deleted_cannot_rollback"));
        }

        return DB::transaction(function () use ($job, $source) {
            $audit = $job->merge_audit ?? [];
            $now = now()->toIso8601String();
            $rollbackDetails = [];

            // 查找所有在这次合并中被迁移的 License
            $migratedLicenseIds = [];
            foreach ($audit as $entry) {
                if (($entry['action'] ?? '') === 'license_merged' && isset($entry['license_id'])) {
                    $migratedLicenseIds[] = $entry['license_id'];
                }
            }

            if (!empty($migratedLicenseIds)) {
                $licenses = License::whereIn('id', $migratedLicenseIds)->get();

                foreach ($licenses as $license) {
                    // 恢复 customer_id 到源客户
                    $oldCustomerId = $license->customer_id;
                    $license->update(['customer_id' => $source->id]);

                    // 恢复设备 tenant_id
                    Device::where('license_id', $license->id)
                        ->update(['tenant_id' => $source->tenant_id]);

                    // 记录回滚审计
                    $meta = $license->metadata ?? [];
                    $meta['merge_history'] = $meta['merge_history'] ?? [];
                    $meta['merge_history'][] = [
                        'action' => 'rollback',
                        'merge_job_id' => $job->id,
                        'from_customer_id' => $oldCustomerId,
                        'to_customer_id' => $source->id,
                        'rolled_back_at' => $now,
                    ];
                    $license->update(['metadata' => $meta]);

                    $rollbackDetails[] = $license->id;
                }
            }

            // 恢复源客户标记
            $source->update([
                'merged_into_customer_id' => null,
            ]);

            $audit[] = [
                'action' => 'rolled_back',
                'licenses_restored' => count($rollbackDetails),
                'at' => $now,
            ];

            $job->update([
                'status' => 'rolled_back',
                'merge_audit' => $audit,
            ]);

            return $job->fresh();
        });
    }
}
