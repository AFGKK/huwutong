<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Events\LicenseStatusChanged;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * M1.2-01 License 状态机
 *
 * 封装 8 状态严格转移矩阵，提供：
 * - 状态转移校验（含原因说明）
 * - 批量状态转移
 * - 转移前/后钩子
 * - 转移审计日志
 * - 可达状态查询
 */
class LicenseStatusMachine
{
    /**
     * 严格状态转移矩阵
     */
    private const TRANSITIONS = [
        'pending' => ['active', 'revoked', 'blacklisted'],
        'active' => ['suspended', 'frozen', 'expired', 'revoked', 'refunded', 'blacklisted'],
        'suspended' => ['active', 'frozen', 'expired', 'revoked', 'refunded', 'blacklisted'],
        'frozen' => ['active', 'suspended', 'expired', 'revoked', 'refunded', 'blacklisted'],
        'expired' => ['active', 'revoked', 'refunded', 'blacklisted'],  // 续费后恢复 active
        'revoked' => ['blacklisted'],
        'refunded' => ['blacklisted'],
        'blacklisted' => [],  // 终态
    ];

    /**
     * 各转移的默认原因模板
     */
    private const REASON_TEMPLATES = [
        'pending->active' => 'License 激活',
        'pending->revoked' => '激活前撤销',
        'pending->blacklisted' => '激活前加入黑名单',
        'active->suspended' => '管理员挂起',
        'active->frozen' => '风控/法律冻结',
        'active->expired' => 'License 到期',
        'active->revoked' => 'License 撤销',
        'active->refunded' => '客户退款',
        'active->blacklisted' => '加入黑名单',
        'suspended->active' => '解除挂起',
        'suspended->frozen' => '挂起后冻结',
        'suspended->expired' => '挂起后过期',
        'suspended->revoked' => '挂起后撤销',
        'suspended->refunded' => '挂起后退款',
        'suspended->blacklisted' => '挂起后加入黑名单',
        'frozen->active' => '解除冻结',
        'frozen->suspended' => '冻结后挂起',
        'frozen->expired' => '冻结后过期',
        'frozen->revoked' => '冻结后撤销',
        'frozen->refunded' => '冻结后退款',
        'frozen->blacklisted' => '冻结后加入黑名单',
        'expired->active' => '续费恢复',
        'expired->revoked' => '过期后撤销',
        'expired->refunded' => '过期后退款',
        'expired->blacklisted' => '过期后加入黑名单',
        'revoked->blacklisted' => '撤销后加入黑名单',
        'refunded->blacklisted' => '退款后加入黑名单',
    ];

    /**
     * 判断是否允许状态转移
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function canTransition(string|LicenseStatus $from, string|LicenseStatus $to): array
    {
        $fromStr = $from instanceof LicenseStatus ? $from->value : $from;
        $toStr = $to instanceof LicenseStatus ? $to->value : $to;

        $allowed = self::TRANSITIONS[$fromStr] ?? [];

        if (in_array($toStr, $allowed, true)) {
            $templateKey = "{$fromStr}->{$toStr}";
            return [
                'allowed' => true,
                'reason' => self::REASON_TEMPLATES[$templateKey] ?? "状态变更: {$fromStr} → {$toStr}",
            ];
        }

        // 生成拒绝原因
        if (LicenseStatus::tryFrom($toStr) === null) {
            return ['allowed' => false, 'reason' => "无效的目标状态: {$toStr}"];
        }

        if ($fromStr === $toStr) {
            return ['allowed' => false, 'reason' => "状态未发生变化: {$fromStr}"];
        }

        if (($fromStr === 'blacklisted')) {
            return ['allowed' => false, 'reason' => '黑名单为终态，不可转移'];
        }

        $available = array_map(fn($s) => self::REASON_TEMPLATES["{$fromStr}->{$s}"] ?? $s, $allowed);
        return [
            'allowed' => false,
            'reason' => "不允许从 {$fromStr} 转移到 {$toStr}。允许的转移: " . implode('、', $available),
        ];
    }

    /**
     * 执行状态转移
     *
     * @throws \RuntimeException 当转移不允许时
     */
    public function transition(License $license, string|LicenseStatus $to, ?User $operator = null, ?string $reason = null): License
    {
        $from = $license->status;
        $check = $this->canTransition($from, $to);

        if (!$check['allowed']) {
            throw new \RuntimeException($check['reason']);
        }

        $toStr = $to instanceof LicenseStatus ? $to->value : $to;
        $finalReason = $reason ?: $check['reason'];

        return DB::transaction(function () use ($license, $from, $toStr, $operator, $finalReason) {
            // 前钩子
            $this->beforeTransition($license, $toStr, $operator, $finalReason);

            // 执行转移
            $oldStatus = $license->status;
            $license->update(['status' => $toStr]);

            // 记录审计日志
            $this->logTransition($license, $oldStatus, $toStr, $operator, $finalReason);

            // 触发事件
            Event::dispatch(new LicenseStatusChanged($license, $oldStatus, $toStr, $operator));

            // 后钩子
            $this->afterTransition($license, $oldStatus, $toStr, $operator);

            Log::info("License 状态变更: {$license->license_key} {$oldStatus} → {$toStr}", [
                'license_id' => $license->id,
                'operator' => $operator?->id,
                'reason' => $finalReason,
            ]);

            return $license->fresh();
        });
    }

    /**
     * 批量状态转移
     *
     * @param License[] $licenses
     * @return array{success: array, failed: array}
     */
    public function batchTransition(
        iterable $licenses,
        string|LicenseStatus $to,
        ?User $operator = null,
        ?string $reason = null
    ): array {
        $result = ['success' => [], 'failed' => []];

        foreach ($licenses as $license) {
            try {
                $this->transition($license, $to, $operator, $reason);
                $result['success'][] = $license->id;
            } catch (\Exception $e) {
                $result['failed'][] = [
                    'id' => $license->id,
                    'license_key' => $license->license_key,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * 获取从指定状态可以转移到的所有状态
     */
    public function getAllowedTransitions(string|LicenseStatus $from): array
    {
        $fromStr = $from instanceof LicenseStatus ? $from->value : $from;
        $allowed = self::TRANSITIONS[$fromStr] ?? [];

        return array_map(function ($status) use ($fromStr) {
            $key = "{$fromStr}->{$status}";
            return [
                'to' => $status,
                'label' => self::REASON_TEMPLATES[$key] ?? $status,
            ];
        }, $allowed);
    }

    /**
     * 获取完整的转移矩阵
     */
    public function getTransitionMatrix(): array
    {
        $matrix = [];
        foreach (self::TRANSITIONS as $from => $toList) {
            $matrix[$from] = array_map(function ($to) use ($from) {
                $key = "{$from}->{$to}";
                return [
                    'to' => $to,
                    'label' => self::REASON_TEMPLATES[$key] ?? $to,
                ];
            }, $toList);
        }
        return $matrix;
    }

    /**
     * 转移前钩子
     */
    protected function beforeTransition(License $license, string $toStatus, ?User $operator, string $reason): void
    {
        // 子类可覆写此方法添加前置校验
    }

    /**
     * 转移后钩子
     */
    protected function afterTransition(License $license, string $oldStatus, string $newStatus, ?User $operator): void
    {
        // 子类可覆写此方法添加后置处理
    }

    /**
     * 记录状态转移审计日志
     */
    protected function logTransition(License $license, string $from, string $to, ?User $operator, string $reason): void
    {
        // 写入审计日志表（如果存在 audit_logs 关联）
        if (method_exists($license, 'auditLogs')) {
            $license->auditLogs()->create([
                'action' => 'status_changed',
                'description' => "状态变更: {$from} → {$to}",
                'old_value' => $from,
                'new_value' => $to,
                'reason' => $reason,
                'operator_id' => $operator?->id,
                'operator_name' => $operator?->name,
            ]);
        }

        Log::info("LicenseStatusMachine: {$license->license_key} {$from} → {$to}", [
            'license_id' => $license->id,
            'operator' => $operator?->id,
            'reason' => $reason,
        ]);
    }
}
