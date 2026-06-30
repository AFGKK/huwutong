<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Refund;
use App\Models\RefundRiskAssessment;
use App\Models\RefundRiskRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 自动退款 + 风控决策引擎
 *
 * 提供：
 * - 退款申请风控评估（风险评分、自动决策）
 * - 审核工作流（自动批准/拒绝/人工审核）
 * - 部分退款比例推荐
 * - 退款规则管理
 */
class RefundEngineService
{
    // 风险等级阈值
    const RISK_THRESHOLDS = [
        'low' => 30,
        'medium' => 60,
        'high' => 80,
        'critical' => 100,
    ];

    /**
     * 评估退款请求风险
     */
    public function assess(Refund $refund): RefundRiskAssessment
    {
        $factors = [];
        $riskScore = 0;
        $matchedRules = [];

        // 1. 评估各风险因子
        $factors[] = $this->evaluateTimeWindow($refund);
        $factors[] = $this->evaluateFrequency($refund);
        $factors[] = $this->evaluateCustomerTier($refund);
        $factors[] = $this->evaluateLicenseAge($refund);
        $factors[] = $this->evaluateAmountRatio($refund);
        $factors[] = $this->evaluateInvoiceStatus($refund);

        // 计算总分
        foreach ($factors as $factor) {
            $riskScore += $factor['score'] ?? 0;
        }
        $riskScore = min(100, max(0, $riskScore));

        // 2. 匹配风控规则
        $rules = RefundRiskRule::where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            $matched = $this->matchRule($rule, $refund, $factors);
            if ($matched) {
                $matchedRules[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'actions' => $rule->actions,
                ];
            }
        }

        // 3. 确定风险等级
        $riskLevel = $this->determineRiskLevel($riskScore);

        // 4. 生成决策
        $decision = $this->generateDecision($riskLevel, $matchedRules, $refund);

        // 5. 创建评估记录
        $assessment = RefundRiskAssessment::create([
            'assessable_type' => Refund::class,
            'assessable_id' => $refund->id,
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'factors' => $factors,
            'matched_rules' => $matchedRules,
            'decision' => $decision['action'],
            'review_status' => $decision['action'] === 'require_review' ? 'pending' : ($decision['action'] === 'auto_reject' ? 'rejected' : 'approved'),
        ]);

        // 6. 关联到退款记录
        $refund->update([
            'risk_assessment_id' => $assessment->id,
            'auto_decision' => $decision['action'],
        ]);

        Log::info('退款风控评估完成', [
            'refund_id' => $refund->id,
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'decision' => $decision['action'],
            'reason' => $decision['reason'],
        ]);

        return $assessment;
    }

    /**
     * 执行退款自动决策（根据风控评估结果）
     */
    public function executeDecision(Refund $refund): array
    {
        $assessment = $refund->riskAssessment;
        if (!$assessment) {
            return ['executed' => false, 'message' => '尚未进行风控评估'];
        }

        $decision = $assessment->decision;

        return match ($decision) {
            'auto_approve' => $this->approveRefund($refund, $assessment),
            'auto_reject' => $this->rejectRefund($refund, $assessment),
            'partial_refund' => $this->partialRefund($refund, $assessment),
            'require_review' => [
                'executed' => false,
                'action' => 'require_review',
                'message' => '需人工审核',
                'assessment_id' => $assessment->id,
            ],
            default => [
                'executed' => false,
                'message' => '未知决策类型',
            ],
        };
    }

    /**
     * 审核退款（人工）
     */
    public function review(Refund $refund, string $action, int $reviewerId, string $note = null): RefundRiskAssessment
    {
        $assessment = $refund->riskAssessment;
        if (!$assessment) {
            throw new \RuntimeException('尚未进行风控评估');
        }

        $assessment->update([
            'review_status' => $action === 'approve' ? 'approved' : 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        if ($action === 'approve') {
            $this->approveRefund($refund, $assessment);
        } else {
            $this->rejectRefund($refund, $assessment);
        }

        return $assessment->fresh();
    }

    // ═════════ 规则管理 ═════════

    /**
     * 获取默认风控规则
     */
    public function getDefaultRules(): array
    {
        return [
            [
                'name' => '购买7天内退款自动批准',
                'rule_type' => 'time_window',
                'conditions' => ['type' => 'days_since_purchase', 'operator' => 'lte', 'value' => 7],
                'actions' => [['type' => 'auto_approve']],
                'priority' => 10,
            ],
            [
                'name' => '大额退款需人工审核',
                'rule_type' => 'amount_threshold',
                'conditions' => ['type' => 'amount', 'operator' => 'gte', 'value' => 5000],
                'actions' => [['type' => 'require_review']],
                'priority' => 30,
            ],
            [
                'name' => '频繁退款自动拒绝',
                'rule_type' => 'frequency',
                'conditions' => ['type' => 'refund_count_30d', 'operator' => 'gte', 'value' => 3],
                'actions' => [['type' => 'auto_reject']],
                'priority' => 20,
            ],
            [
                'name' => '高风险客户需审核',
                'rule_type' => 'customer_tier',
                'conditions' => ['type' => 'customer_risk', 'value' => 'high'],
                'actions' => [['type' => 'require_review']],
                'priority' => 40,
            ],
            [
                'name' => '老客户满30天自动批准',
                'rule_type' => 'license_age',
                'conditions' => ['type' => 'license_days', 'operator' => 'gte', 'value' => 30],
                'actions' => [['type' => 'auto_approve']],
                'priority' => 50,
            ],
            [
                'name' => '超过60天License退款需审核',
                'rule_type' => 'license_age',
                'conditions' => ['type' => 'license_days', 'operator' => 'gt', 'value' => 60],
                'actions' => [['type' => 'require_review']],
                'priority' => 60,
            ],
            [
                'name' => '同一客户单月超3次退款自动拒绝',
                'rule_type' => 'frequency',
                'conditions' => ['type' => 'monthly_refund_count', 'operator' => 'gte', 'value' => 3],
                'actions' => [['type' => 'auto_reject']],
                'priority' => 15,
            ],
            [
                'name' => '退款金额超过发票金额拒绝',
                'rule_type' => 'amount_threshold',
                'conditions' => ['type' => 'amount_vs_invoice', 'operator' => 'gt', 'value' => 1.0],
                'actions' => [['type' => 'auto_reject']],
                'priority' => 5,
            ],
        ];
    }

    /**
     * 初始化默认规则
     */
    public function seedDefaultRules(): void
    {
        foreach ($this->getDefaultRules() as $rule) {
            RefundRiskRule::firstOrCreate(
                ['name' => $rule['name']],
                $rule
            );
        }
    }

    /**
     * 获取风控统计
     */
    public function getRiskStats(): array
    {
        return [
            'total_assessments' => RefundRiskAssessment::count(),
            'by_risk_level' => RefundRiskAssessment::selectRaw('risk_level, count(*) as count')
                ->groupBy('risk_level')->pluck('count', 'risk_level')->toArray(),
            'by_decision' => RefundRiskAssessment::selectRaw('decision, count(*) as count')
                ->groupBy('decision')->pluck('count', 'decision')->toArray(),
            'pending_review' => RefundRiskAssessment::where('review_status', 'pending')->count(),
            'recent_rejections' => RefundRiskAssessment::where('decision', 'auto_reject')
                ->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    // ═══════════ M3-11 增强 ═══════════

    /**
     * 退款引擎仪表盘
     */
    public function getDashboard(): array
    {
        $riskStats = $this->getRiskStats();

        // 今日/本周/本月 数据
        $today = Refund::whereDate('created_at', now()->today());
        $thisWeek = Refund::where('created_at', '>=', now()->startOfWeek());
        $thisMonth = Refund::whereMonth('created_at', now()->month);

        $dashboard = [
            'today' => [
                'count' => (clone $today)->count(),
                'amount' => (clone $today)->where('status', 'completed')->sum('amount'),
                'auto_approved' => (clone $today)->where('auto_decision', 'auto_approve')->count(),
                'auto_rejected' => (clone $today)->where('auto_decision', 'auto_reject')->count(),
            ],
            'this_week' => [
                'count' => (clone $thisWeek)->count(),
                'amount' => (clone $thisWeek)->where('status', 'completed')->sum('amount'),
            ],
            'this_month' => [
                'count' => (clone $thisMonth)->count(),
                'amount' => (clone $thisMonth)->where('status', 'completed')->sum('amount'),
            ],
            'pending_count' => Refund::where('status', 'pending')->count(),
            'pending_risk_review' => RefundRiskAssessment::where('review_status', 'pending')
                ->where('decision', 'require_review')->count(),
        ];

        // 风险趋势 (近7天)
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayAssessments = RefundRiskAssessment::whereDate('created_at', $date);
            $trend[] = [
                'date' => $date->format('Y-m-d'),
                'total' => (clone $dayAssessments)->count(),
                'approved' => (clone $dayAssessments)->where('decision', 'auto_approve')->count(),
                'rejected' => (clone $dayAssessments)->where('decision', 'auto_reject')->count(),
                'review' => (clone $dayAssessments)->where('decision', 'require_review')->count(),
            ];
        }

        return array_merge($dashboard, $riskStats, ['trend' => $trend]);
    }

    /**
     * 获取推荐的部分退款比例
     */
    public function getRecommendedPartialAmount(Refund $refund): float
    {
        $invoice = $refund->invoice;
        if (!$invoice || $invoice->amount <= 0) {
            return round($refund->amount * 0.5, 2);
        }

        $daysSincePaid = $invoice->paid_at?->diffInDays(now()) ?? 0;

        // 按使用时长递减退款比例
        if ($daysSincePaid <= 7) return round($refund->amount, 2);
        if ($daysSincePaid <= 30) return round($refund->amount * 0.8, 2);
        if ($daysSincePaid <= 60) return round($refund->amount * 0.5, 2);
        if ($daysSincePaid <= 90) return round($refund->amount * 0.3, 2);
        return round($refund->amount * 0.1, 2);
    }

    // ═════════ 私有方法 ═════════

    /**
     * 评估：购买时间窗口
     */
    protected function evaluateTimeWindow(Refund $refund): array
    {
        $invoice = $refund->invoice;
        if (!$invoice || !$invoice->paid_at) {
            return ['name' => 'time_window', 'score' => 20, 'detail' => '无法确定购买时间'];
        }

        $daysSincePaid = $invoice->paid_at->diffInDays(now());

        if ($daysSincePaid <= 7) {
            return ['name' => 'time_window', 'score' => 0, 'detail' => "购买{$daysSincePaid}天内，低风险"];
        }
        if ($daysSincePaid <= 30) {
            return ['name' => 'time_window', 'score' => 15, 'detail' => "购买{$daysSincePaid}天，中风险"];
        }
        if ($daysSincePaid <= 60) {
            return ['name' => 'time_window', 'score' => 30, 'detail' => "购买{$daysSincePaid}天，较高风险"];
        }
        return ['name' => 'time_window', 'score' => 40, 'detail' => "购买{$daysSincePaid}天，高风险"];
    }

    /**
     * 评估：退款频率
     */
    protected function evaluateFrequency(Refund $refund): array
    {
        $customerId = $refund->customer_id;
        if (!$customerId) {
            return ['name' => 'frequency', 'score' => 0, 'detail' => '无客户信息'];
        }

        $count30d = Refund::where('customer_id', $customerId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $countMonthly = Refund::where('customer_id', $customerId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonth())
            ->count();

        $score = min(40, $count30d * 15);
        return [
            'name' => 'frequency',
            'score' => $score,
            'detail' => "30天内{$count30d}次退款，月内{$countMonthly}次",
        ];
    }

    /**
     * 评估：客户等级
     */
    protected function evaluateCustomerTier(Refund $refund): array
    {
        $customer = $refund->customer;
        if (!$customer) {
            return ['name' => 'customer_tier', 'score' => 10, 'detail' => '无客户信息'];
        }

        // 根据客户累计消费评估风险
        $totalSpent = Invoice::where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->sum('amount');

        if ($totalSpent > 10000) {
            return ['name' => 'customer_tier', 'score' => 0, 'detail' => "高价值客户(累计消费{$totalSpent}元)"];
        }
        if ($totalSpent > 1000) {
            return ['name' => 'customer_tier', 'score' => 5, 'detail' => "中等价值客户(累计消费{$totalSpent}元)"];
        }
        return ['name' => 'customer_tier', 'score' => 15, 'detail' => "低价值客户(累计消费{$totalSpent}元)"];
    }

    /**
     * 评估：License 使用时长
     */
    protected function evaluateLicenseAge(Refund $refund): array
    {
        $license = $refund->license;
        if (!$license) {
            return ['name' => 'license_age', 'score' => 10, 'detail' => '无License信息'];
        }

        $daysSinceCreated = $license->created_at->diffInDays(now());

        if ($daysSinceCreated <= 30) {
            return ['name' => 'license_age', 'score' => 5, 'detail' => "License创建{$daysSinceCreated}天"];
        }
        if ($daysSinceCreated <= 60) {
            return ['name' => 'license_age', 'score' => 15, 'detail' => "License创建{$daysSinceCreated}天"];
        }
        return ['name' => 'license_age', 'score' => 25, 'detail' => "License创建{$daysSinceCreated}天，超60天退款"];
    }

    /**
     * 评估：退款金额占比
     */
    protected function evaluateAmountRatio(Refund $refund): array
    {
        $invoice = $refund->invoice;
        if (!$invoice || $invoice->amount <= 0) {
            return ['name' => 'amount_ratio', 'score' => 10, 'detail' => '无法计算占比'];
        }

        $ratio = $refund->amount / $invoice->amount;

        if ($ratio > 1.0) {
            return ['name' => 'amount_ratio', 'score' => 40, 'detail' => "退款金额({$refund->amount})超过发票金额({$invoice->amount})"];
        }
        if ($ratio > 0.8) {
            return ['name' => 'amount_ratio', 'score' => 15, 'detail' => "全额或接近全额退款({$ratio})"];
        }
        if ($ratio > 0.5) {
            return ['name' => 'amount_ratio', 'score' => 5, 'detail' => "大部退款({$ratio})"];
        }
        return ['name' => 'amount_ratio', 'score' => 0, 'detail' => "小额退款({$ratio})"];
    }

    /**
     * 评估：发票状态
     */
    protected function evaluateInvoiceStatus(Refund $refund): array
    {
        $invoice = $refund->invoice;
        if (!$invoice) {
            return ['name' => 'invoice_status', 'score' => 10, 'detail' => '无发票信息'];
        }

        if ($invoice->status !== 'paid') {
            return ['name' => 'invoice_status', 'score' => 30, 'detail' => "发票状态异常:{$invoice->status}"];
        }

        if ($invoice->refunded_at) {
            return ['name' => 'invoice_status', 'score' => 40, 'detail' => '发票已退过款'];
        }

        return ['name' => 'invoice_status', 'score' => 0, 'detail' => '发票状态正常'];
    }

    /**
     * 匹配规则
     */
    protected function matchRule(RefundRiskRule $rule, Refund $refund, array $factors): bool
    {
        $conditions = $rule->conditions;

        return match ($rule->rule_type) {
            'time_window' => $this->matchTimeWindow($conditions, $refund),
            'amount_threshold' => $this->matchAmountThreshold($conditions, $refund),
            'frequency' => $this->matchFrequency($conditions, $refund),
            'customer_tier' => $this->matchCustomerTier($conditions, $refund),
            'license_age' => $this->matchLicenseAge($conditions, $refund),
            default => false,
        };
    }

    protected function matchTimeWindow(array $conditions, Refund $refund): bool
    {
        $invoice = $refund->invoice;
        if (!$invoice || !$invoice->paid_at) return false;

        $days = $invoice->paid_at->diffInDays(now());
        return $this->compare($days, $conditions['operator'] ?? 'lte', $conditions['value'] ?? 7);
    }

    protected function matchAmountThreshold(array $conditions, Refund $refund): bool
    {
        $type = $conditions['type'] ?? 'amount';
        if ($type === 'amount_vs_invoice') {
            $invoice = $refund->invoice;
            if (!$invoice || $invoice->amount <= 0) return false;
            return $this->compare($refund->amount / $invoice->amount, $conditions['operator'] ?? 'gt', $conditions['value'] ?? 1.0);
        }
        return $this->compare($refund->amount, $conditions['operator'] ?? 'gte', $conditions['value'] ?? 5000);
    }

    protected function matchFrequency(array $conditions, Refund $refund): bool
    {
        $customerId = $refund->customer_id;
        if (!$customerId) return false;

        $type = $conditions['type'] ?? 'refund_count_30d';
        $days = $type === 'monthly_refund_count' ? 30 : 30;
        $count = Refund::where('customer_id', $customerId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        return $this->compare($count, $conditions['operator'] ?? 'gte', $conditions['value'] ?? 3);
    }

    protected function matchCustomerTier(array $conditions, Refund $refund): bool
    {
        $customer = $refund->customer;
        if (!$customer) return false;

        $level = $conditions['value'] ?? 'medium';
        $totalSpent = Invoice::where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->sum('amount');

        return match ($level) {
            'high' => $totalSpent < 100,
            'medium' => $totalSpent < 1000,
            'low' => $totalSpent >= 10000,
            default => false,
        };
    }

    protected function matchLicenseAge(array $conditions, Refund $refund): bool
    {
        $license = $refund->license;
        if (!$license) return false;

        $days = $license->created_at->diffInDays(now());
        return $this->compare($days, $conditions['operator'] ?? 'lte', $conditions['value'] ?? 30);
    }

    protected function compare(mixed $value, string $operator, mixed $target): bool
    {
        return match ($operator) {
            'eq' => $value === $target,
            'neq', 'ne' => $value !== $target,
            'gt' => $value > $target,
            'gte', 'ge' => $value >= $target,
            'lt' => $value < $target,
            'lte', 'le' => $value <= $target,
            default => $value >= $target,
        };
    }

    /**
     * 确定风险等级
     */
    protected function determineRiskLevel(int $score): string
    {
        if ($score >= self::RISK_THRESHOLDS['critical']) return 'critical';
        if ($score >= self::RISK_THRESHOLDS['high']) return 'high';
        if ($score >= self::RISK_THRESHOLDS['medium']) return 'medium';
        return 'low';
    }

    /**
     * 生成决策
     */
    protected function generateDecision(string $riskLevel, array $matchedRules, Refund $refund): array
    {
        // 优先使用规则匹配的决策
        foreach ($matchedRules as $rule) {
            foreach ($rule['actions'] as $action) {
                if (in_array($action['type'], ['auto_approve', 'auto_reject', 'require_review', 'partial_refund'])) {
                    $reason = "规则匹配: {$rule['rule_name']}";
                    return ['action' => $action['type'], 'reason' => $reason];
                }
            }
        }

        // 根据风险等级默认决策
        return match ($riskLevel) {
            'low' => ['action' => 'auto_approve', 'reason' => '低风险，自动批准'],
            'medium' => ['action' => 'auto_approve', 'reason' => '中等风险，自动批准（建议关注）'],
            'high' => ['action' => 'require_review', 'reason' => '高风险，需人工审核'],
            'critical' => ['action' => 'auto_reject', 'reason' => '极高风险，自动拒绝'],
            default => ['action' => 'require_review', 'reason' => '无法判定，需人工审核'],
        };
    }

    /**
     * 自动批准退款
     */
    protected function approveRefund(Refund $refund, RefundRiskAssessment $assessment): array
    {
        DB::transaction(function () use ($refund) {
            $refund->update([
                'status' => 'completed',
                'completed_at' => now(),
                'approved_by' => $refund->processed_by,
                'approved_at' => now(),
            ]);

            if ($refund->invoice) {
                $refund->invoice->update(['refunded_at' => now()]);
            }

            if ($refund->license) {
                $refund->license->update(['status' => 'refunded']);
            }
        });

        return [
            'executed' => true,
            'action' => 'approved',
            'message' => '退款已自动批准',
        ];
    }

    /**
     * 自动拒绝退款
     */
    protected function rejectRefund(Refund $refund, RefundRiskAssessment $assessment): array
    {
        $refund->update([
            'status' => 'failed',
            'failure_reason' => '风控拒绝: ' . ($assessment->review_note ?? '系统自动拒绝'),
        ]);

        return [
            'executed' => true,
            'action' => 'rejected',
            'message' => '退款已被风控拒绝',
        ];
    }

    /**
     * 部分退款
     */
    protected function partialRefund(Refund $refund, RefundRiskAssessment $assessment): array
    {
        $invoice = $refund->invoice;
        $recommendedAmount = $invoice ? min($refund->amount, $invoice->amount * 0.5) : $refund->amount * 0.5;

        return [
            'executed' => false,
            'action' => 'partial_refund',
            'recommended_amount' => round($recommendedAmount, 2),
            'message' => "建议部分退款，推荐金额: {$recommendedAmount}",
        ];
    }
}
