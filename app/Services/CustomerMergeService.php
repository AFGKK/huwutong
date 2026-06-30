<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerMergeLog;
use App\Models\Invoice;
use App\Models\License;
use App\Models\PrepaidBalance;
use App\Models\PrepaidTransaction;
use App\Models\CreditLimit;
use App\Models\Subscription;
use App\Models\CustomFieldValue;
use App\Models\Tag;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 客户合并服务
 *
 * 合并策略：
 * - License / Subscription / Invoice → 全部迁移至目标客户
 * - 预付余额 & 信用额度 → 累加
 * - User 关联 → 各自保留，不做变更
 * - 自定义字段 & 标签 → 迁移至目标客户
 * - 源客户标记为 merged 状态，不可再操作
 */
class CustomerMergeService
{
    /**
     * 预览合并影响（仅查询，不修改数据）
     */
    public function previewMerge(Customer $source, Customer $target): array
    {
        $this->validateMerge($source, $target);

        return [
            'source' => [
                'id' => $source->id,
                'user_id' => $source->user_id,
                'type' => $source->type,
                'level' => $source->level,
                'status' => $source->status,
                'billing_method' => $source->billing_method,
                'prepaid_balance' => (float) ($source->prepaid_balance ?? 0),
                'credit_limit' => (float) ($source->credit_limit ?? 0),
                'credit_used' => (float) ($source->credit_used ?? 0),
            ],
            'target' => [
                'id' => $target->id,
                'user_id' => $target->user_id,
                'type' => $target->type,
                'level' => $target->level,
                'status' => $target->status,
                'billing_method' => $target->billing_method,
                'prepaid_balance' => (float) ($target->prepaid_balance ?? 0),
                'credit_limit' => (float) ($target->credit_limit ?? 0),
                'credit_used' => (float) ($target->credit_used ?? 0),
            ],
            'conflicts' => $this->detectConflicts($source, $target),
            'affected_records' => [
                'licenses' => $source->licenses()->count(),
                'subscriptions' => $source->subscriptions()->count(),
                'invoices' => $source->invoices()->count(),
                'prepaid_transactions' => $source->prepaidTransactions()->count(),
            ],
        ];
    }

    /**
     * 执行客户合并
     */
    public function merge(Customer $source, Customer $target, ?int $mergedByUserId = null, array $conflictResolution = []): CustomerMergeLog
    {
        $this->validateMerge($source, $target);

        return DB::transaction(function () use ($source, $target, $mergedByUserId, $conflictResolution) {
            // 记录合并日志
            $log = CustomerMergeLog::create([
                'tenant_id' => $source->tenant_id,
                'source_customer_id' => $source->id,
                'target_customer_id' => $target->id,
                'status' => 'pending',
                'conflict_resolution' => $conflictResolution,
                'merged_by' => $mergedByUserId,
                'notes' => $conflictResolution['notes'] ?? null,
            ]);

            $errors = [];
            $summary = [
                'licenses_moved' => 0,
                'subscriptions_moved' => 0,
                'invoices_moved' => 0,
                'prepaid_balance_added' => 0,
                'prepaid_transactions_moved' => 0,
                'custom_fields_moved' => 0,
                'custom_fields_conflicted' => 0,
                'prepaid_balance_conflict' => false,
                'credit_limit_conflict' => false,
                'user_conflict' => false,
            ];

            try {
                // 1. 迁移 License
                $licenseCount = $source->licenses()->count();
                if ($licenseCount > 0) {
                    License::where('customer_id', $source->id)
                        ->update(['customer_id' => $target->id]);
                    $summary['licenses_moved'] = $licenseCount;
                }

                // 2. 迁移 Subscription
                $subCount = $source->subscriptions()->count();
                if ($subCount > 0) {
                    Subscription::where('customer_id', $source->id)
                        ->update(['customer_id' => $target->id]);
                    $summary['subscriptions_moved'] = $subCount;
                }

                // 3. 迁移 Invoice
                $invCount = $source->invoices()->count();
                if ($invCount > 0) {
                    Invoice::where('customer_id', $source->id)
                        ->update(['customer_id' => $target->id]);
                    $summary['invoices_moved'] = $invCount;
                }

                // 4. 合并预付余额
                $sourceBalance = (float) ($source->prepaid_balance ?? 0);
                $sourceCreditLimit = (float) ($source->credit_limit ?? 0);
                $sourceCreditUsed = (float) ($source->credit_used ?? 0);

                if ($sourceBalance > 0 || $sourceCreditLimit > 0 || $sourceCreditUsed > 0) {
                    // 累加到目标客户
                    $target->increment('prepaid_balance', $sourceBalance);
                    $target->increment('credit_limit', $sourceCreditLimit);
                    $target->increment('credit_used', $sourceCreditUsed);

                    $summary['prepaid_balance_added'] = $sourceBalance;
                    $summary['credit_limit_conflict'] = $sourceCreditLimit > 0 || $sourceCreditUsed > 0;
                }

                // 5. 迁移预付交易记录
                $prepaidTxCount = $source->prepaidTransactions()->count();
                if ($prepaidTxCount > 0) {
                    PrepaidTransaction::where('customer_id', $source->id)
                        ->update(['customer_id' => $target->id]);
                    $summary['prepaid_transactions_moved'] = $prepaidTxCount;
                }

                // 6. 迁移预付余额记录
                PrepaidBalance::where('customer_id', $source->id)
                    ->update(['customer_id' => $target->id]);

                // 7. 迁移信用额度记录
                CreditLimit::where('customer_id', $source->id)
                    ->update(['customer_id' => $target->id]);

                // 8. 迁移自定义字段（morphMany）
                $cfMoved = CustomFieldValue::where('fieldable_type', Customer::class)
                    ->where('fieldable_id', $source->id)
                    ->update(['fieldable_id' => $target->id]);
                $summary['custom_fields_moved'] = $cfMoved;

                // 9. 迁移标签（taggable morph）
                $source->tags()->detach();
                // 将源客户的标签附加到目标客户（去重）
                $sourceTags = $source->tags()->pluck('tags.id')->toArray();
                $targetExistingTagIds = $target->tags()->pluck('tags.id')->toArray();
                $newTagIds = array_diff($sourceTags, $targetExistingTagIds);
                if (!empty($newTagIds)) {
                    $target->tags()->attach($newTagIds);
                }

                // 10. 标记源客户为已合并
                $source->update([
                    'status' => 'merged',
                    'merged_into_customer_id' => $target->id,
                ]);

                // 11. 递增目标客户的合并计数
                $target->increment('merge_count');

                // 处理冲突检测（记录日志但不阻断）
                $conflicts = $this->detectConflicts($source, $target);
                if (!empty($conflicts)) {
                    foreach ($conflicts as $conflict) {
                        $summary[$conflict['field'] . '_conflict'] = true;
                    }
                }

                // 更新合并日志
                $log->update([
                    'status' => 'completed',
                    'summary' => $summary,
                    'merged_at' => now(),
                ]);

            } catch (Exception $e) {
                $errors[] = $e->getMessage();
                $log->update([
                    'status' => 'failed',
                    'errors' => $errors,
                ]);
                Log::error('CustomerMerge failed', [
                    'source_id' => $source->id,
                    'target_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            return $log->fresh();
        });
    }

    /**
     * 验证合并前提条件
     */
    protected function validateMerge(Customer $source, Customer $target): void
    {
        if ($source->id === $target->id) {
            throw new Exception('不能将客户合并到自身');
        }

        if ($source->tenant_id !== $target->tenant_id) {
            throw new Exception('只能合并同一租户下的客户');
        }

        if ($source->status === 'merged') {
            throw new Exception('源客户已经是已合并状态，不能再次合并');
        }

        if ($target->status === 'merged') {
            throw new Exception('目标客户已经是已合并状态（源客户已合并到其他账号）');
        }

        if ($source->merged_into_customer_id) {
            throw new Exception('源客户已经被合并到其他账号，不能再次作为源');
        }
    }

    /**
     * 检测冲突（仅记录，不阻断）
     */
    protected function detectConflicts(Customer $source, Customer $target): array
    {
        $conflicts = [];

        if ($source->type !== $target->type) {
            $conflicts[] = ['field' => 'type', 'source' => $source->type, 'target' => $target->type];
        }
        if ($source->level !== $target->level) {
            $conflicts[] = ['field' => 'level', 'source' => $source->level, 'target' => $target->level];
        }
        if ($source->billing_method !== $target->billing_method) {
            $conflicts[] = ['field' => 'billing_method', 'source' => $source->billing_method, 'target' => $target->billing_method];
        }
        if ($source->user_id && $target->user_id && $source->user_id !== $target->user_id) {
            $conflicts[] = ['field' => 'user_id', 'source' => $source->user_id, 'target' => $target->user_id];
        }

        return $conflicts;
    }

    /**
     * 查询合并历史
     */
    public function getMergeHistory(int $tenantId, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return CustomerMergeLog::where('tenant_id', $tenantId)
            ->with(['sourceCustomer', 'targetCustomer', 'mergedBy'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * 获取合并详情
     */
    public function getMergeDetail(CustomerMergeLog $log): CustomerMergeLog
    {
        return $log->load(['sourceCustomer', 'targetCustomer', 'mergedBy']);
    }
}
