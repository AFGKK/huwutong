<?php

namespace App\Services;

use App\Models\GdprDataRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 账号注销流水线服务（M3-62 数据匿名化处理）
 *
 * 提供完整的账号注销流程：
 * 1. 验证：检查账号是否可注销（无未结清账单/无活跃订阅）
 * 2. 数据导出：注销前为用户生成数据副本
 * 3. 数据匿名化：覆盖所有相关表进行 PII 脱敏
 * 4. 通知：发送注销确认通知
 */
class AccountDeletionService
{
    const CANCELLATION_REASONS = [
        'too_expensive' => '价格过高',
        'missing_features' => '缺少所需功能',
        'switching_provider' => '更换服务商',
        'no_longer_needed' => '不再需要',
        'technical_issues' => '技术问题',
        'poor_support' => '客服体验差',
        'privacy_concerns' => '隐私顾虑',
        'other' => '其他原因',
    ];

    public function __construct(
        protected GdprComplianceService $gdprService,
    ) {}

    /**
     * 检查账号是否可注销
     *
     * @return array ['can_delete' => bool, 'reasons' => string[]]
     */
    public function checkDeletability(User $user): array
    {
        $reasons = [];

        // 检查是否有未结清账单
        $customer = $user->customer;
        if ($customer) {
            $unpaidInvoices = $customer->invoices()
                ->whereIn('status', ['pending', 'overdue'])
                ->count();
            if ($unpaidInvoices > 0) {
                $reasons[] = "有 {$unpaidInvoices} 笔未结清账单，请先完成支付";
            }

            // 检查是否有活跃订阅
            $activeSubscriptions = $customer->subscriptions()
                ->whereIn('status', ['active', 'trial', 'grace'])
                ->count();
            if ($activeSubscriptions > 0) {
                $reasons[] = "有 {$activeSubscriptions} 个活跃订阅，请先取消订阅";
            }
        }

        // 检查是否有未处理的工单
        $openTickets = \App\Models\Ticket::where('user_id', $user->id)
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->count();
        if ($openTickets > 0) {
            $reasons[] = "有 {$openTickets} 个未处理工单，请先关闭";
        }

        // 检查是否有待处理的提现
        $pendingPayouts = \App\Models\CommissionPayout::whereHas('agent', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->whereIn('status', ['pending', 'pending_review'])->count();
        if ($pendingPayouts > 0) {
            $reasons[] = "有待处理提现 {$pendingPayouts} 笔，请先完成";
        }

        return [
            'can_delete' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * 执行账号注销全流程
     *
     * 1. 创建 GDPR data request 记录
     * 2. 数据导出（为用户保留副本）
     * 3. 完整匿名化所有相关数据
     * 4. 更新 GDPR request 状态
     *
     * @return array 包含注销记录和匿名化统计
     */
    public function deleteAccount(User $user, string $reason = 'user_requested', ?string $reasonDetail = null): array
    {
        return DB::transaction(function () use ($user, $reason, $reasonDetail) {
            // 1. 创建 GDPR 删除请求
            $gdprRequest = GdprDataRequest::create([
                'user_id' => $user->id,
                'type' => GdprDataRequest::TYPE_ERASURE,
                'status' => GdprDataRequest::STATUS_PROCESSING,
                'reason' => $reasonDetail ?? self::CANCELLATION_REASONS[$reason] ?? $reason,
                'request_data' => [
                    'cancellation_reason' => $reason,
                    'cancellation_detail' => $reasonDetail,
                    'initiated_by' => 'user',
                    'initiated_at' => now()->toIso8601String(),
                ],
            ]);

            // 2. 数据导出（保留30天）
            try {
                $personalData = $this->gdprService->collectPersonalData($user);
                $fileName = "pre-deletion-export-{$user->id}-" . now()->format('YmdHis') . '.json';
                $path = "gdpr-exports/{$fileName}";
                \Illuminate\Support\Facades\Storage::disk('local')
                    ->put($path, json_encode($personalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $gdprRequest->update([
                    'output_file' => $path,
                    'file_size' => \Illuminate\Support\Facades\Storage::disk('local')->size($path),
                ]);
            } catch (\Throwable $e) {
                Log::warning('账号注销数据导出失败', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // 3. 完整匿名化 — 调用增强版 GdprComplianceService
            $anonymizationResults = $this->gdprService->anonymizeUserData($user);

            // 4. 标记用户为已注销
            $user->updateQuietly([
                'status' => 'deleted',
                'email' => 'deleted_' . $user->id . '@anonymized.local',
            ]);

            // 5. 完成 GDPR request
            $gdprRequest->update([
                'status' => GdprDataRequest::STATUS_COMPLETED,
                'completed_at' => now(),
                'request_data' => array_merge($gdprRequest->request_data ?? [], [
                    'anonymization_results' => $anonymizationResults,
                ]),
            ]);

            Log::info('账号已完整注销（M3-62）', [
                'user_id' => $user->id,
                'gdpr_request_id' => $gdprRequest->id,
                'anonymization' => $anonymizationResults,
                'reason' => $reason,
            ]);

            return [
                'gdpr_request_id' => $gdprRequest->id,
                'anonymized_tables' => $anonymizationResults,
                'export_file' => $gdprRequest->output_file,
            ];
        });
    }

    /**
     * 管理员手动发起数据匿名化
     */
    public function adminAnonymizeUser(User $user, int $adminId, ?string $notes = null): array
    {
        return DB::transaction(function () use ($user, $adminId, $notes) {
            $gdprRequest = GdprDataRequest::create([
                'user_id' => $user->id,
                'type' => GdprDataRequest::TYPE_ERASURE,
                'status' => GdprDataRequest::STATUS_PROCESSING,
                'processed_by' => $adminId,
                'reason' => $notes ?? '管理员手动触发的数据匿名化',
                'request_data' => [
                    'initiated_by' => 'admin',
                    'admin_id' => $adminId,
                    'initiated_at' => now()->toIso8601String(),
                ],
            ]);

            $results = $this->gdprService->anonymizeUserData($user);

            $user->updateQuietly([
                'status' => 'deleted',
                'email' => 'deleted_' . $user->id . '@anonymized.local',
            ]);

            $gdprRequest->update([
                'status' => GdprDataRequest::STATUS_COMPLETED,
                'completed_at' => now(),
                'request_data' => array_merge($gdprRequest->request_data ?? [], [
                    'anonymization_results' => $results,
                ]),
            ]);

            return [
                'gdpr_request_id' => $gdprRequest->id,
                'anonymized_tables' => $results,
            ];
        });
    }
}
