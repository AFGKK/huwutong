<?php

namespace App\Services;

use App\Models\BatchJob;
use App\Models\BatchJobItem;
use App\Models\BatchSnapshot;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Subscription;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 批量操作工具服务 (M2-08)
 *
 * 支持对 License、Subscription、Customer、Invoice、Ticket 等模型
 * 执行批量激活/续期/导出/挂起/吊销/删除/状态变更等操作，
 * 含操作预览、进度跟踪、错误详情、快照回滚。
 */
class BatchService
{
    /**
     * 预览批量操作影响范围
     *
     * @param string $targetModel 模型类型（licenses/subscriptions/customers/invoices/tickets）
     * @param array|null $ids 指定 ID 列表
     * @param array|null $filters 筛选条件
     * @param array $params 操作参数
     * @return array {total_count, sample: Collection, has_more}
     */
    public function preview(
        string $targetModel,
        ?array $ids = null,
        ?array $filters = null,
        array $params = []
    ): array {
        $query = $this->buildQuery($targetModel, $filters, $ids);

        $totalCount = $query->count();
        $sample = (clone $query)->limit(10)->get();

        return [
            'total_count' => $totalCount,
            'sample' => $sample,
            'has_more' => $totalCount > 10,
        ];
    }

    /**
     * 执行批量操作
     *
     * @param int $tenantId
     * @param int|null $userId
     * @param string $type 操作类型
     * @param string $targetModel 目标模型
     * @param array|null $ids 指定 ID 列表
     * @param array|null $filters 筛选条件
     * @param array $params 操作参数
     * @return BatchJob
     */
    public function execute(
        int $tenantId,
        ?int $userId,
        string $type,
        string $targetModel,
        ?array $ids = null,
        ?array $filters = null,
        array $params = []
    ): BatchJob {
        // 1. 创建批量任务记录
        $batchJob = BatchJob::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $type,
            'target_model' => $targetModel,
            'filters' => $filters,
            'ids' => $ids,
            'params' => $params,
            'status' => BatchJob::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        // 2. 获取目标列表
        $targets = $this->resolveTargets($tenantId, $targetModel, $ids, $filters);
        $batchJob->update(['total_count' => $targets->count()]);

        // 3. 逐项执行
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($targets as $target) {
            try {
                $result = DB::transaction(function () use ($type, $target, $batchJob, $params) {
                    return $this->executeSingle($type, $target, $batchJob, $params);
                });

                BatchJobItem::create([
                    'batch_job_id' => $batchJob->id,
                    'targetable_type' => get_class($target),
                    'targetable_id' => $target->id,
                    'status' => BatchJobItem::STATUS_SUCCESS,
                    'result_data' => $result,
                ]);
                $successCount++;
            } catch (\Throwable $e) {
                BatchJobItem::create([
                    'batch_job_id' => $batchJob->id,
                    'targetable_type' => get_class($target),
                    'targetable_id' => $target->id,
                    'status' => BatchJobItem::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
                $failCount++;
                $errors[] = "ID#{$target->id}: {$e->getMessage()}";
            }
        }

        // 4. 汇总更新
        $status = $failCount === 0 ? BatchJob::STATUS_COMPLETED
            : ($successCount > 0 ? BatchJob::STATUS_COMPLETED : BatchJob::STATUS_FAILED);

        $batchJob->update([
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'status' => $status,
            'error_summary' => count($errors) > 0 ? implode(' | ', array_slice($errors, 0, 20)) : null,
            'result_summary' => [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'total_count' => $targets->count(),
                'has_errors' => $failCount > 0,
            ],
            'completed_at' => now(),
        ]);

        Log::info('Batch: operation completed', [
            'batch_job_id' => $batchJob->id,
            'type' => $type,
            'target_model' => $targetModel,
            'success' => $successCount,
            'fail' => $failCount,
        ]);

        return $batchJob->fresh();
    }

    /**
     * 撤销批量操作
     */
    public function undo(BatchJob $batchJob): array
    {
        if (!$batchJob->isReversible()) {
            throw new \InvalidArgumentException('该操作类型不可撤销');
        }

        if (!$batchJob->isFinished()) {
            throw new \InvalidArgumentException('操作尚未完成，无法撤销');
        }

        $snapshots = BatchSnapshot::where('batch_job_id', $batchJob->id)->get();
        $restored = 0;
        $failed = 0;

        foreach ($snapshots as $snapshot) {
            try {
                $target = $snapshot->targetable;
                if ($target) {
                    $target->update([$snapshot->field => $snapshot->old_value]);
                    $restored++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Batch: undo failed', [
                    'snapshot_id' => $snapshot->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'restored' => $restored,
            'failed' => $failed,
            'total' => $snapshots->count(),
        ];
    }

    /**
     * 导出批量操作结果
     */
    public function export(BatchJob $batchJob, string $format = 'csv'): string
    {
        $items = $batchJob->items()->with('targetable')->get();

        $rows = [];
        foreach ($items as $item) {
            $target = $item->targetable;
            if (!$target) continue;

            $rows[] = [
                'id' => $target->id,
                'type' => class_basename($target),
                'status' => $item->status,
                'error' => $item->error_message,
                'result' => json_encode($item->result_data),
            ];
        }

        $filename = "batch_{$batchJob->type}_{$batchJob->id}_" . now()->format('YmdHis') . ".{$format}";

        if ($format === 'csv') {
            $csv = $this->arrayToCsv($rows);
            Storage::disk('local')->put("exports/{$filename}", $csv);
        } else {
            Storage::disk('local')->put("exports/{$filename}", json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $batchJob->update(['export_path' => "exports/{$filename}"]);

        return "exports/{$filename}";
    }

    // ─── 内部方法 ───

    /**
     * 构建查询
     */
    protected function buildQuery(string $targetModel, ?array $filters, ?array $ids)
    {
        $modelClass = $this->resolveModelClass($targetModel);
        $query = $modelClass::query();

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if ($value === null) continue;
                if (str_ends_with($key, '_min')) {
                    $query->where(substr($key, 0, -4), '>=', $value);
                } elseif (str_ends_with($key, '_max')) {
                    $query->where(substr($key, 0, -4), '<=', $value);
                } elseif (is_array($value)) {
                    $query->whereIn($key, $value);
                } else {
                    $query->where($key, $value);
                }
            }
        }

        return $query;
    }

    /**
     * 解析目标列表
     */
    protected function resolveTargets(int $tenantId, string $targetModel, ?array $ids, ?array $filters): Collection
    {
        $query = $this->buildQuery($targetModel, $filters, $ids);
        $query->where('tenant_id', $tenantId);

        return $query->get();
    }

    /**
     * 执行单个操作
     */
    protected function executeSingle(string $type, Model $target, BatchJob $batchJob, array $params): ?array
    {
        $result = null;

        switch ($type) {
            case BatchJob::TYPE_BATCH_ACTIVATE:
                $result = $this->activateTarget($target, $batchJob, $params);
                break;

            case BatchJob::TYPE_BATCH_RENEW:
                $result = $this->renewTarget($target, $batchJob, $params);
                break;

            case BatchJob::TYPE_BATCH_SUSPEND:
                $result = $this->suspendTarget($target, $batchJob);
                break;

            case BatchJob::TYPE_BATCH_REVOKE:
                $result = $this->revokeTarget($target, $batchJob);
                break;

            case BatchJob::TYPE_BATCH_DELETE:
                $result = $this->deleteTarget($target, $batchJob);
                break;

            case BatchJob::TYPE_BATCH_CHANGE_STATUS:
                $result = $this->changeStatus($target, $batchJob, $params);
                break;

            case BatchJob::TYPE_BATCH_CHANGE_PLAN:
                $result = $this->changePlan($target, $batchJob, $params);
                break;

            case BatchJob::TYPE_BATCH_EXPORT:
                // 导出由外部处理
                break;

            default:
                throw new \InvalidArgumentException("未知操作类型: {$type}");
        }

        return $result;
    }

    /**
     * 批量激活目标
     */
    protected function activateTarget(Model $target, BatchJob $batchJob, array $params): array
    {
        $oldStatus = $target->status ?? null;

        if ($target instanceof License) {
            $this->takeSnapshot($batchJob, $target, 'status', $target->status);
            $target->update(['status' => 'active', 'activated_at' => $target->activated_at ?? now()]);
        } elseif ($target instanceof Subscription) {
            $this->takeSnapshot($batchJob, $target, 'status', $target->status);
            $target->update(['status' => 'active']);
        } else {
            throw new \InvalidArgumentException('不支持的激活目标类型');
        }

        return ['old_status' => $oldStatus, 'new_status' => 'active'];
    }

    /**
     * 批量续期
     */
    protected function renewTarget(Model $target, BatchJob $batchJob, array $params): array
    {
        $days = $params['days'] ?? 365;
        $newStatus = $params['new_status'] ?? 'active';

        if ($target instanceof License) {
            // 状态校验：仅 active/expired 可续期
            $allowedStatuses = ['active', 'expired', 'suspended'];
            if (!in_array($target->status, $allowedStatuses)) {
                throw new \InvalidArgumentException("License #{$target->id} 状态 [{$target->status}] 不允许续期");
            }

            $oldExpiresAt = $target->expires_at ? $target->expires_at->toIso8601String() : null;
            $this->takeSnapshot($batchJob, $target, 'expires_at', $target->expires_at);
            $this->takeSnapshot($batchJob, $target, 'status', $target->status);

            $newExpiresAt = $target->expires_at && $target->expires_at > now()
                ? $target->expires_at->copy()->addDays($days)
                : now()->addDays($days);

            $target->update([
                'status' => $newStatus,
                'expires_at' => $newExpiresAt,
            ]);

            // 记录审计日志
            activity()
                ->performedOn($target)
                ->causedBy($batchJob->user)
                ->withProperties([
                    'action' => 'batch_renew',
                    'batch_job_id' => $batchJob->id,
                    'old_expires_at' => $oldExpiresAt,
                    'new_expires_at' => $newExpiresAt->toIso8601String(),
                    'days_added' => $days,
                ])
                ->log('license:batch_renew');

            return [
                'old_expires_at' => $oldExpiresAt,
                'new_expires_at' => $newExpiresAt->toIso8601String(),
                'days_added' => $days,
            ];
        }

        if ($target instanceof Subscription) {
            $oldExpiresAt = $target->ends_at ? $target->ends_at->toIso8601String() : null;
            $this->takeSnapshot($batchJob, $target, 'ends_at', $target->ends_at);
            $this->takeSnapshot($batchJob, $target, 'status', $target->status);

            $newEndsAt = $target->ends_at && $target->ends_at > now()
                ? $target->ends_at->copy()->addDays($days)
                : now()->addDays($days);

            $target->update([
                'status' => $newStatus,
                'ends_at' => $newEndsAt,
            ]);

            return [
                'old_expires_at' => $oldExpiresAt,
                'new_expires_at' => $newEndsAt->toIso8601String(),
                'days_added' => $days,
            ];
        }

        throw new \InvalidArgumentException('不支持的续期目标类型');
    }

    /**
     * 批量挂起
     */
    protected function suspendTarget(Model $target, BatchJob $batchJob): array
    {
        if (!$target instanceof License && !$target instanceof Subscription) {
            throw new \InvalidArgumentException('不支持的挂起目标类型');
        }

        $this->takeSnapshot($batchJob, $target, 'status', $target->status ?? 'active');
        $target->update(['status' => 'suspended']);

        return ['old_status' => $target->getOriginal('status'), 'new_status' => 'suspended'];
    }

    /**
     * 批量吊销
     */
    protected function revokeTarget(Model $target, BatchJob $batchJob): array
    {
        if (!$target instanceof License) {
            throw new \InvalidArgumentException('仅 License 支持吊销');
        }

        $this->takeSnapshot($batchJob, $target, 'status', $target->status);
        $target->update(['status' => 'revoked']);

        return ['old_status' => $target->getOriginal('status'), 'new_status' => 'revoked'];
    }

    /**
     * 批量删除（软删除）
     */
    protected function deleteTarget(Model $target, BatchJob $batchJob): array
    {
        $target->delete();

        return ['deleted' => true];
    }

    /**
     * 批量状态变更
     */
    protected function changeStatus(Model $target, BatchJob $batchJob, array $params): array
    {
        $newStatus = $params['status'] ?? null;
        if (!$newStatus) {
            throw new \InvalidArgumentException('未指定目标状态');
        }

        $this->takeSnapshot($batchJob, $target, 'status', $target->status);
        $target->update(['status' => $newStatus]);

        return ['old_status' => $target->getOriginal('status'), 'new_status' => $newStatus];
    }

    /**
     * 批量变更计划
     */
    protected function changePlan(Model $target, BatchJob $batchJob, array $params): array
    {
        if (!$target instanceof Subscription) {
            throw new \InvalidArgumentException('仅 Subscription 支持变更计划');
        }

        $newPlan = $params['plan'] ?? null;

        if ($newPlan) {
            $this->takeSnapshot($batchJob, $target, 'plan', $target->plan);
            $target->update(['plan' => $newPlan]);
        }

        if (isset($params['price'])) {
            $this->takeSnapshot($batchJob, $target, 'price', $target->price);
            $target->update(['price' => $params['price']]);
        }

        return [
            'old_plan' => $target->getOriginal('plan'),
            'new_plan' => $newPlan,
        ];
    }

    /**
     * 创建快照（用于撤销）
     */
    protected function takeSnapshot(BatchJob $batchJob, Model $target, string $field, mixed $oldValue): void
    {
        if (!$batchJob->isReversible()) return;

        BatchSnapshot::create([
            'batch_job_id' => $batchJob->id,
            'targetable_type' => get_class($target),
            'targetable_id' => $target->id,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $target->{$field},
        ]);
    }

    /**
     * 解析模型类名
     */
    protected function resolveModelClass(string $targetModel): string
    {
        return match ($targetModel) {
            BatchJob::TARGET_LICENSES => License::class,
            BatchJob::TARGET_SUBSCRIPTIONS => Subscription::class,
            BatchJob::TARGET_CUSTOMERS => Customer::class,
            BatchJob::TARGET_INVOICES => Invoice::class,
            BatchJob::TARGET_TICKETS => Ticket::class,
            default => throw new \InvalidArgumentException("未知目标模型: {$targetModel}"),
        };
    }

    /**
     * 数组转 CSV
     */
    protected function arrayToCsv(array $data): string
    {
        if (empty($data)) return '';

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($data[0]));

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
