<?php

namespace App\Services;

use App\Models\ChurnPrediction;
use App\Models\Customer;
use App\Models\Device;
use App\Models\HealthScore;
use App\Models\HealthScoreHistory;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 客户健康度评分服务 (M2-29)
 *
 * 综合多维度数据（激活活跃度 25% + 续费健康度 30% + 工单体验 20% + 设备安全 15% + 支付健康度 10%）
 * 计算客户健康分，识别流失风险，生成主动干预建议。
 */
class HealthScoreService
{
    const CACHE_PREFIX = 'health_score:';

    // 维度权重
    const WEIGHT_ACTIVATION = 0.25;
    const WEIGHT_RENEWAL = 0.30;
    const WEIGHT_TICKET = 0.20;
    const WEIGHT_DEVICE = 0.15;
    const WEIGHT_PAYMENT = 0.10;

    // 流失概率阈值
    const CHURN_THRESHOLD_LOW = 0.3;
    const CHURN_THRESHOLD_MEDIUM = 0.5;
    const CHURN_THRESHOLD_HIGH = 0.75;

    const GRADE_HEALTHY = 'healthy';
    const GRADE_WARNING = 'warning';
    const GRADE_CRITICAL = 'critical';

    /**
     * 计算单个客户健康分
     */
    public function calculateForCustomer(Customer $customer): HealthScore
    {
        $tenantId = $customer->tenant_id;
        $now = now();

        // 1. 计算各维度得分
        $activationScore = $this->calculateActivationScore($customer);
        $renewalScore = $this->calculateRenewalScore($customer);
        $ticketScore = $this->calculateTicketScore($customer);
        $deviceScore = $this->calculateDeviceScore($customer);
        $paymentScore = $this->calculatePaymentScore($customer);

        // 2. 综合加权
        $score = round(
            $activationScore * self::WEIGHT_ACTIVATION
            + $renewalScore * self::WEIGHT_RENEWAL
            + $ticketScore * self::WEIGHT_TICKET
            + $deviceScore * self::WEIGHT_DEVICE
            + $paymentScore * self::WEIGHT_PAYMENT,
            2
        );

        // 3. 等级评定
        $grade = $this->determineGrade($score);

        // 4. 风险预警和建议
        $warnings = $this->generateWarnings($customer, compact(
            'activationScore', 'renewalScore', 'ticketScore', 'deviceScore', 'paymentScore'
        ));
        $suggestions = $this->generateSuggestions($warnings);

        // 5. 评分因子明细
        $factors = $this->buildFactors($customer);

        // 6. 保存评分
        $healthScore = HealthScore::updateOrCreate(
            ['tenant_id' => $tenantId, 'customer_id' => $customer->id],
            [
                'score' => $score,
                'grade' => $grade,
                'activation_score' => $activationScore,
                'renewal_score' => $renewalScore,
                'ticket_score' => $ticketScore,
                'device_score' => $deviceScore,
                'payment_score' => $paymentScore,
                'factors' => $factors,
                'warnings' => $warnings,
                'suggestions' => $suggestions,
                'calculated_at' => $now,
            ]
        );

        // 7. 记录历史
        HealthScoreHistory::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'score' => $score,
            'grade' => $grade,
            'factors' => $factors,
            'calculated_at' => $now,
        ]);

        // 8. 生成流失预测
        $this->predictChurn($customer, $healthScore);

        Log::info('HealthScore: calculated', [
            'customer_id' => $customer->id,
            'score' => $score,
            'grade' => $grade,
            'warnings_count' => count($warnings),
        ]);

        return $healthScore->fresh();
    }

    /**
     * 批量计算所有活跃客户健康分
     */
    public function calculateAll(int $tenantId, ?int $batchSize = 50): array
    {
        $stats = ['processed' => 0, 'failed' => 0, 'healthy' => 0, 'warning' => 0, 'critical' => 0];

        Customer::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->chunk($batchSize, function (Collection $customers) use (&$stats) {
                foreach ($customers as $customer) {
                    try {
                        $hs = $this->calculateForCustomer($customer);
                        $stats[$hs->grade]++;
                        $stats['processed']++;
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                        Log::error('HealthScore: batch calculation failed', [
                            'customer_id' => $customer->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('HealthScore: batch calculation completed', $stats);
        return $stats;
    }

    /**
     * 获取客户健康度趋势（近 N 次评分历史）
     */
    public function getTrend(Customer $customer, int $limit = 30): Collection
    {
        return HealthScoreHistory::where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('calculated_at')
            ->limit($limit)
            ->get(['score', 'grade', 'calculated_at']);
    }

    /**
     * 获取健康度看板统计
     */
    public function getDashboardStats(int $tenantId): array
    {
        $latest = HealthScore::where('tenant_id', $tenantId)
            ->whereRaw('calculated_at = (SELECT MAX(calculated_at) FROM health_scores h2 WHERE h2.customer_id = health_scores.customer_id)');

        $total = (clone $latest)->count();
        $healthy = (clone $latest)->where('grade', self::GRADE_HEALTHY)->count();
        $warning = (clone $latest)->where('grade', self::GRADE_WARNING)->count();
        $critical = (clone $latest)->where('grade', self::GRADE_CRITICAL)->count();

        $avgScore = (float) (clone $latest)->avg('score');

        // 流失风险客户
        $highRiskChurn = ChurnPrediction::where('tenant_id', $tenantId)
            ->whereIn('risk_level', [ChurnPrediction::RISK_HIGH, ChurnPrediction::RISK_CRITICAL])
            ->where('predicted_at', '>=', now()->subDays(1))
            ->count();

        // 各维度平均分
        $avgActivation = (float) (clone $latest)->avg('activation_score');
        $avgRenewal = (float) (clone $latest)->avg('renewal_score');
        $avgTicket = (float) (clone $latest)->avg('ticket_score');
        $avgDevice = (float) (clone $latest)->avg('device_score');
        $avgPayment = (float) (clone $latest)->avg('payment_score');

        return [
            'total_customers' => $total,
            'healthy' => $healthy,
            'warning' => $warning,
            'critical' => $critical,
            'avg_score' => round($avgScore, 2),
            'high_risk_churn' => $highRiskChurn,
            'dimension_averages' => [
                'activation' => round($avgActivation, 2),
                'renewal' => round($avgRenewal, 2),
                'ticket' => round($avgTicket, 2),
                'device' => round($avgDevice, 2),
                'payment' => round($avgPayment, 2),
            ],
        ];
    }

    // ─── 各维度评分计算 ───

    /**
     * 激活活跃度 (25%)：最近7天活跃天数、License 激活率、使用频率
     */
    protected function calculateActivationScore(Customer $customer): float
    {
        $now = now();
        $score = 50.0; // 默认中等

        $licenses = License::where('customer_id', $customer->id)->get();
        $activeLicenses = $licenses->where('status', 'active');
        $totalLicenses = $licenses->count();

        // License 激活率（最多 +25 分）
        if ($totalLicenses > 0) {
            $activationRate = $activeLicenses->count() / $totalLicenses;
            $score += $activationRate * 25;
        } else {
            $score -= 20; // 无 License 扣分
        }

        // 最近活跃设备数（最多 +15 分）
        $recentDevices = Device::whereIn('license_id', $licenses->pluck('id'))
            ->where('last_seen_at', '>=', $now->copy()->subDays(7))
            ->count();
        $score += min($recentDevices * 5, 15);

        // 最后活跃时间（最多 +10 分）
        $lastSeen = Device::whereIn('license_id', $licenses->pluck('id'))
            ->max('last_seen_at');
        if ($lastSeen) {
            $daysSinceLastSeen = $lastSeen->diffInDays($now);
            if ($daysSinceLastSeen <= 1) $score += 10;
            elseif ($daysSinceLastSeen <= 3) $score += 7;
            elseif ($daysSinceLastSeen <= 7) $score += 4;
            else $score -= 5; // 超过一周未活跃
        }

        return max(0, min(100, round($score, 2)));
    }

    /**
     * 续费健康度 (30%)：订阅状态、续费成功率、剩余天数
     */
    protected function calculateRenewalScore(Customer $customer): float
    {
        $score = 50.0;
        $now = now();

        $subscriptions = Subscription::where('customer_id', $customer->id)->get();

        if ($subscriptions->isEmpty()) {
            return 30; // 无订阅 → 低分
        }

        $activeSubs = $subscriptions->whereIn('status', ['active', 'grace']);
        $expiredSubs = $subscriptions->where('status', 'expired');
        $autoRenewSubs = $subscriptions->where('auto_renew', true);

        // 活跃订阅比例（最多 +20 分）
        $activeRate = $subscriptions->count() > 0
            ? $activeSubs->count() / $subscriptions->count()
            : 0;
        $score += $activeRate * 20;

        // 自动续费比例（最多 +15 分）
        $autoRenewRate = $subscriptions->count() > 0
            ? $autoRenewSubs->count() / $subscriptions->count()
            : 0;
        $score += $autoRenewRate * 15;

        // 过期比例扣分（最多 -20 分）
        $expiredRate = $subscriptions->count() > 0
            ? $expiredSubs->count() / $subscriptions->count()
            : 0;
        $score -= $expiredRate * 20;

        // 近期到期状态（最多 +15 分）
        foreach ($activeSubs as $sub) {
            if ($sub->ends_at) {
                $daysRemaining = $now->diffInDays($sub->ends_at, false);
                if ($daysRemaining > 30) $score += 5;
                elseif ($daysRemaining <= 0) $score -= 10; // 已过期
                elseif ($daysRemaining <= 7) $score -= 5;  // 7天内到期
            }
        }

        // 宽限期扣分
        $graceSubs = $subscriptions->where('status', 'grace');
        $score -= $graceSubs->count() * 5;

        return max(0, min(100, round($score, 2)));
    }

    /**
     * 工单体验 (20%)：近30天工单数、响应时间、解决率、满意度
     */
    protected function calculateTicketScore(Customer $customer): float
    {
        $score = 75.0; // 默认较高（无工单 = 体验良好）
        $now = now();

        $recentTickets = Ticket::where('customer_id', $customer->id)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->get();

        if ($recentTickets->isEmpty()) {
            return $score; // 没有工单 = 高健康度
        }

        $openTickets = $recentTickets->where('status', 'open');
        $resolvedTickets = $recentTickets->whereIn('status', ['resolved', 'closed']);
        $totalCount = $recentTickets->count();

        // 未解决工单数扣分（每个 -5 分）
        $score -= $openTickets->count() * 5;

        // 解决率加分（最多 +15 分）
        $resolutionRate = $totalCount > 0 ? $resolvedTickets->count() / $totalCount : 0;
        $score += $resolutionRate * 15;

        // 平均响应时间扣分
        $ticketsWithResponse = $recentTickets->whereNotNull('first_response_at');
        if ($ticketsWithResponse->isNotEmpty()) {
            $avgResponseMins = $ticketsWithResponse->avg(fn ($t) => $t->getResponseTimeMinutes() ?? 0);
            if ($avgResponseMins > 120) $score -= 10;       // >2小时响应 → 扣分
            elseif ($avgResponseMins > 60) $score -= 5;     // >1小时
        }

        // 高优先级工单额外扣分
        $highPriority = $recentTickets->whereIn('priority', ['high', 'urgent'])
            ->where('status', 'open');
        $score -= $highPriority->count() * 8;

        return max(0, min(100, round($score, 2)));
    }

    /**
     * 设备安全 (15%)：黑名单设备、虚拟设备、信任分、设备数异常
     */
    protected function calculateDeviceScore(Customer $customer): float
    {
        $score = 70.0;
        $licenses = License::where('customer_id', $customer->id)->pluck('id');

        if ($licenses->isEmpty()) {
            return 50;
        }

        $devices = Device::whereIn('license_id', $licenses)->get();
        $totalDevices = $devices->count();

        if ($totalDevices === 0) {
            return 50;
        }

        // 黑名单设备（每个 -20 分）
        $blacklisted = $devices->where('is_blacklisted', true)->count();
        $score -= $blacklisted * 20;

        // 虚拟设备（每个 -10 分）
        $virtualDevices = $devices->where('is_virtual', true)->count();
        $score -= $virtualDevices * 10;

        // 平均信任分（最多 +20 分）
        $avgTrust = $devices->avg('trust_score') ?? 0;
        $score += ($avgTrust / 100) * 20;

        // 设备数量异常检测（超过平均太多扣分）
        $licenseCount = $licenses->count();
        $avgDevicesPerLicense = $licenseCount > 0 ? $totalDevices / $licenseCount : 0;
        if ($avgDevicesPerLicense > 10) $score -= 15;       // 异常
        elseif ($avgDevicesPerLicense > 5) $score -= 5;

        return max(0, min(100, round($score, 2)));
    }

    /**
     * 支付健康度 (10%)：欠费、支付失败、账单逾期
     */
    protected function calculatePaymentScore(Customer $customer): float
    {
        $score = 80.0;

        $pendingInvoices = Invoice::where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->where('due_at', '<', now())
            ->get();

        // 逾期账单扣分
        $overdueCount = $pendingInvoices->count();
        $score -= $overdueCount * 15;

        // 逾期超过30天额外扣分
        $longOverdue = $pendingInvoices->filter(fn ($inv) => $inv->due_at && $inv->due_at->diffInDays(now(), false) > 30);
        $score -= $longOverdue->count() * 10;

        // 总欠款金额扣分
        $totalOverdue = (float) $pendingInvoices->sum('amount');
        if ($totalOverdue > 10000) $score -= 15;
        elseif ($totalOverdue > 1000) $score -= 5;

        // 历史支付失败（从 metadata 中提取，简化处理）
        $failedInvoices = Invoice::where('customer_id', $customer->id)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(90))
            ->count();
        $score -= $failedInvoices * 10;

        return max(0, min(100, round($score, 2)));
    }

    // ─── 辅助方法 ───

    /**
     * 根据综合分判定等级
     */
    protected function determineGrade(float $score): string
    {
        if ($score >= HealthScore::THRESHOLD_HEALTHY) return self::GRADE_HEALTHY;
        if ($score >= HealthScore::THRESHOLD_WARNING) return self::GRADE_WARNING;
        return self::GRADE_CRITICAL;
    }

    /**
     * 构建评分因子明细
     */
    protected function buildFactors(Customer $customer): array
    {
        $now = now();

        return [
            'licenses' => [
                'total' => License::where('customer_id', $customer->id)->count(),
                'active' => License::where('customer_id', $customer->id)->where('status', 'active')->count(),
            ],
            'subscriptions' => [
                'total' => Subscription::where('customer_id', $customer->id)->count(),
                'active' => Subscription::where('customer_id', $customer->id)->whereIn('status', ['active', 'grace'])->count(),
                'auto_renew' => Subscription::where('customer_id', $customer->id)->where('auto_renew', true)->count(),
                'expired' => Subscription::where('customer_id', $customer->id)->where('status', 'expired')->count(),
            ],
            'tickets_30d' => Ticket::where('customer_id', $customer->id)
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->count(),
            'devices' => [
                'total' => Device::whereIn('license_id', License::where('customer_id', $customer->id)->pluck('id'))->count(),
                'blacklisted' => Device::whereIn('license_id', License::where('customer_id', $customer->id)->pluck('id'))
                    ->where('is_blacklisted', true)->count(),
            ],
            'invoices' => [
                'overdue' => Invoice::where('customer_id', $customer->id)
                    ->where('status', 'pending')
                    ->where('due_at', '<', $now)
                    ->count(),
                'failed_90d' => Invoice::where('customer_id', $customer->id)
                    ->where('status', 'failed')
                    ->where('created_at', '>=', $now->copy()->subDays(90))
                    ->count(),
            ],
        ];
    }

    /**
     * 生成风险预警
     */
    protected function generateWarnings(Customer $customer, array $scores): array
    {
        $warnings = [];

        if ($scores['activationScore'] < 40) {
            $warnings[] = [
                'type' => 'low_activation',
                'dimension' => 'activation',
                'severity' => 'high',
                'message' => 'License 激活率低，客户可能未充分使用产品',
            ];
        }

        if ($scores['renewalScore'] < 40) {
            $warnings[] = [
                'type' => 'renewal_risk',
                'dimension' => 'renewal',
                'severity' => 'critical',
                'message' => '续费健康度差，存在高流失风险',
            ];
        }

        // 检查是否有过期订阅
        $expiredSubs = Subscription::where('customer_id', $customer->id)
            ->where('status', 'expired')->count();
        if ($expiredSubs > 0) {
            $warnings[] = [
                'type' => 'expired_subscription',
                'dimension' => 'renewal',
                'severity' => 'critical',
                'message' => "有 {$expiredSubs} 个订阅已过期",
            ];
        }

        if ($scores['ticketScore'] < 50) {
            $warnings[] = [
                'type' => 'poor_ticket_experience',
                'dimension' => 'ticket',
                'severity' => 'medium',
                'message' => '工单处理体验差，近30天有未解决工单',
            ];
        }

        if ($scores['deviceScore'] < 40) {
            $warnings[] = [
                'type' => 'device_security_risk',
                'dimension' => 'device',
                'severity' => 'high',
                'message' => '设备安全分低，存在黑名单或虚拟设备',
            ];
        }

        if ($scores['paymentScore'] < 40) {
            $warnings[] = [
                'type' => 'payment_overdue',
                'dimension' => 'payment',
                'severity' => 'critical',
                'message' => '存在逾期未付账单',
            ];
        }

        // 检查现金流失
        $highPriorityOpen = Ticket::where('customer_id', $customer->id)
            ->whereIn('priority', ['high', 'urgent'])
            ->where('status', 'open')
            ->count();
        if ($highPriorityOpen > 0) {
            $warnings[] = [
                'type' => 'unresolved_high_priority',
                'dimension' => 'ticket',
                'severity' => 'high',
                'message' => "有 {$highPriorityOpen} 个高优先级工单未解决",
            ];
        }

        return $warnings;
    }

    /**
     * 根据预警生成干预建议
     */
    protected function generateSuggestions(array $warnings): array
    {
        $suggestions = [];

        foreach ($warnings as $w) {
            $suggestion = match ($w['type']) {
                'low_activation' => [
                    'action' => '发送使用引导',
                    'detail' => '推送产品使用教程/最佳实践，安排客户成功经理一对一沟通',
                    'priority' => 'medium',
                ],
                'renewal_risk' => [
                    'action' => '主动续费跟进',
                    'detail' => '提前联系客户了解续费意向，提供优惠方案或年度折扣',
                    'priority' => 'high',
                ],
                'expired_subscription' => [
                    'action' => '流失客户挽回',
                    'detail' => '发送专属回归优惠，了解流失原因，安排销售跟进',
                    'priority' => 'high',
                ],
                'poor_ticket_experience' => [
                    'action' => '工单回访',
                    'detail' => '主动回访未关闭工单客户，加速问题解决流程',
                    'priority' => 'medium',
                ],
                'device_security_risk' => [
                    'action' => '设备安全审查',
                    'detail' => '通知客户存在异常设备，建议检查并清理黑名单设备',
                    'priority' => 'high',
                ],
                'payment_overdue' => [
                    'action' => '催缴通知',
                    'detail' => '发送逾期账单提醒，提供延期支付选项',
                    'priority' => 'high',
                ],
                'unresolved_high_priority' => [
                    'action' => '紧急工单处理',
                    'detail' => '升级高优先级工单，安排技术支持优先处理',
                    'priority' => 'critical',
                ],
                default => [
                    'action' => '定期回访',
                    'detail' => '安排常规客户满意度回访',
                    'priority' => 'low',
                ],
            };

            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    // ─── 流失预测 ───

    /**
     * 基于健康分和因子生成流失预测
     */
    protected function predictChurn(Customer $customer, HealthScore $healthScore): ChurnPrediction
    {
        $now = now();
        $score = (float) $healthScore->score;

        // 基于综合分的流失概率（分数越低概率越高）
        $churnProbability = $this->estimateChurnProbability($healthScore);

        // 识别主要流失信号
        $topSignals = [];
        if ($healthScore->renewal_score < 40) {
            $topSignals[] = 'renewal_score_low';
        }
        if ($healthScore->payment_score < 40) {
            $topSignals[] = 'payment_overdue';
        }
        if ($healthScore->activation_score < 40) {
            $topSignals[] = 'low_activation';
        }
        if ($healthScore->ticket_score < 40) {
            $topSignals[] = 'ticket_frustration';
        }

        // 等级判断
        $riskLevel = $this->determineChurnRiskLevel($churnProbability);

        // 干预建议
        $recommendations = $this->generateChurnRecommendations($riskLevel, $topSignals);

        return ChurnPrediction::updateOrCreate(
            ['tenant_id' => $customer->tenant_id, 'customer_id' => $customer->id],
            [
                'churn_probability' => $churnProbability,
                'risk_level' => $riskLevel,
                'top_signals' => $topSignals,
                'recommendations' => $recommendations,
                'predicted_at' => $now,
            ]
        );
    }

    /**
     * 估算流失概率（基于健康分和其他因子）
     */
    protected function estimateChurnProbability(HealthScore $hs): float
    {
        $score = (float) $hs->score;

        // sigmoid-like: 分数越低流失概率越高
        // score=100 → prob≈0.05, score=50 → prob≈0.5, score=0 → prob≈0.95
        $baseProb = 1 / (1 + exp(($score - 50) / 15));

        // 加权因子：支付逾期 +0.15，续费问题 +0.15
        $adjustment = 0;
        if ((float) $hs->payment_score < 30) $adjustment += 0.15;
        if ((float) $hs->renewal_score < 30) $adjustment += 0.15;
        if ((float) $hs->activation_score < 20) $adjustment += 0.1;

        return round(min(0.98, $baseProb + $adjustment), 4);
    }

    /**
     * 流失风险等级判定
     */
    protected function determineChurnRiskLevel(float $probability): string
    {
        if ($probability >= self::CHURN_THRESHOLD_HIGH) return ChurnPrediction::RISK_CRITICAL;
        if ($probability >= self::CHURN_THRESHOLD_MEDIUM) return ChurnPrediction::RISK_HIGH;
        if ($probability >= self::CHURN_THRESHOLD_LOW) return ChurnPrediction::RISK_MEDIUM;
        return ChurnPrediction::RISK_LOW;
    }

    /**
     * 生成流失干预建议
     */
    protected function generateChurnRecommendations(string $riskLevel, array $signals): array
    {
        if ($riskLevel === ChurnPrediction::RISK_LOW) {
            return [['action' => '常规维护', 'detail' => '保持定期沟通，无明显流失风险']];
        }

        $recommendations = [];

        if (in_array('renewal_score_low', $signals)) {
            $recommendations[] = ['action' => '续费优惠', 'detail' => '提供限时续费折扣或赠送额外服务周期'];
        }
        if (in_array('payment_overdue', $signals)) {
            $recommendations[] = ['action' => '支付协商', 'detail' => '联系客户协商分期付款或延期方案'];
        }
        if (in_array('low_activation', $signals)) {
            $recommendations[] = ['action' => '启用引导', 'detail' => '安排产品培训，帮助客户充分使用产品功能'];
        }
        if (in_array('ticket_frustration', $signals)) {
            $recommendations[] = ['action' => '客户成功', 'detail' => '安排客户成功经理回访，解决产品使用障碍'];
        }

        if ($riskLevel === ChurnPrediction::RISK_CRITICAL || $riskLevel === ChurnPrediction::RISK_HIGH) {
            $recommendations[] = ['action' => '高层介入', 'detail' => '安排销售总监或客户成功总监直接联系'];
            $recommendations[] = ['action' => '满意度调研', 'detail' => '发送 NPS 问卷，了解真实流失原因'];
        }

        return $recommendations;
    }
}
