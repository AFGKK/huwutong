<?php

namespace App\Services;

use App\Models\ChurnPrediction;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\RfmScore;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CrmService
{
    /**
     * 重新计算所有客户的 RFM 评分
     */
    public function recalculateAllRfm(?int $tenantId = null): array
    {
        $query = Customer::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $count = 0;
        $query->chunk(100, function ($customers) use (&$count, $tenantId) {
            foreach ($customers as $customer) {
                $this->calculateRfm($customer);
                $count++;
            }
        });

        return ['processed' => $count];
    }

    /**
     * 计算单个客户的 RFM 评分
     */
    public function calculateRfm(Customer $customer): RfmScore
    {
        $tenantId = $customer->tenant_id;

        // Recency: 最近一次发票日期
        $lastInvoice = Invoice::where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->latest('paid_at')
            ->first();

        $recencyDays = $lastInvoice?->paid_at
            ? now()->startOfDay()->diffInDays($lastInvoice->paid_at)
            : 999;

        $recencyScore = $this->scoreRecency($recencyDays);

        // Frequency: 已支付发票数 + 活跃订阅数
        $paidInvoices = Invoice::where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->count();

        $activeSubscriptions = Subscription::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->count();

        $frequencyCount = $paidInvoices + $activeSubscriptions;
        $frequencyScore = $this->scoreFrequency($frequencyCount);

        // Monetary: 总支付金额
        $monetaryTotal = Invoice::where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->sum('amount') ?: 0;

        $monetaryScore = $this->scoreMonetary((float) $monetaryTotal);
        $rfmTotal = $recencyScore + $frequencyScore + $monetaryScore;
        $rfmSegment = $this->getRfmSegment($rfmTotal, $recencyScore, $frequencyScore, $monetaryScore);

        return RfmScore::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'tenant_id' => $tenantId,
                'recency_days' => $recencyDays >= 999 ? null : $recencyDays,
                'recency_score' => $recencyScore,
                'frequency_count' => $frequencyCount,
                'frequency_score' => $frequencyScore,
                'monetary_total' => $monetaryTotal,
                'monetary_score' => $monetaryScore,
                'rfm_total' => $rfmTotal,
                'rfm_segment' => $rfmSegment,
                'calculated_at' => now(),
            ]
        );
    }

    /**
     * 更新所有动态分群
     */
    public function refreshAllSegments(?int $tenantId = null): array
    {
        $segments = CustomerSegment::where('is_active', true)
            ->where('is_dynamic', true)
            ->get();

        $results = [];
        foreach ($segments as $segment) {
            $results[$segment->slug] = $this->refreshSegment($segment, $tenantId);
        }

        return $results;
    }

    /**
     * 刷新单个分群的成员
     */
    public function refreshSegment(CustomerSegment $segment, ?int $tenantId = null): int
    {
        if (! $segment->is_dynamic || ! $segment->rules) {
            return 0;
        }

        $query = Customer::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $matchedIds = [];
        $query->chunk(200, function ($customers) use ($segment, &$matchedIds) {
            foreach ($customers as $customer) {
                if ($segment->matchesCustomer($customer)) {
                    $matchedIds[] = $customer->id;
                }
            }
        });

        // 同步关联
        $segment->customers()->sync($matchedIds);
        $segment->update(['member_count' => count($matchedIds)]);

        return count($matchedIds);
    }

    /**
     * 对单个客户进行流失预测
     */
    public function predictChurn(Customer $customer): ChurnPrediction
    {
        $signals = [];
        $score = 0;

        // 1. RFM 信号
        $rfm = RfmScore::where('customer_id', $customer->id)->first();
        if ($rfm) {
            if ($rfm->recency_days !== null && $rfm->recency_days > 90) {
                $score += 20;
                $signals[] = '超过 90 天未购买';
            } elseif ($rfm->recency_days !== null && $rfm->recency_days > 30) {
                $score += 10;
                $signals[] = '超过 30 天未购买';
            }

            if ($rfm->frequency_count <= 1) {
                $score += 10;
                $signals[] = '仅有一次购买记录';
            }

            if ($rfm->monetary_total < 100) {
                $score += 10;
                $signals[] = '历史消费金额偏低';
            }
        }

        // 2. 订阅状态
        $activeSub = Subscription::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->exists();

        $hasExpired = Subscription::where('customer_id', $customer->id)
            ->whereIn('status', ['expired', 'canceled'])
            ->exists();

        if (! $activeSub) {
            $score += 25;
            $signals[] = '无活跃订阅';
        }

        if ($hasExpired && ! $activeSub) {
            $score += 15;
            $signals[] = '有已过期订阅且无活跃订阅';
        }

        // 3. 无活跃 License
        $hasActiveLicense = $customer->licenses()
            ->where('status', 'active')
            ->exists();

        if (! $hasActiveLicense) {
            $score += 10;
            $signals[] = '无活跃 License';
        }

        // 4. 客户状态
        if ($customer->status === 'inactive' || $customer->status === 'suspended') {
            $score += 10;
            $signals[] = "客户状态: {$customer->status}";
        }

        // 计算风险等级
        $risk = $this->getChurnRiskLevel($score);

        // 预测流失日期
        $predictedDate = null;
        if ($risk === 'high' || $risk === 'critical') {
            $predictedDate = now()->addDays(max(30, 90 - $score))->toDateString();
        }

        // 建议挽留措施
        $action = $this->getRetentionAction($risk, $signals);

        return ChurnPrediction::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'tenant_id' => $customer->tenant_id,
                'churn_score' => min($score, 100),
                'churn_risk' => $risk,
                'predicted_churn_date' => $predictedDate,
                'signals' => $signals,
                'recommended_action' => $action,
                'predicted_at' => now(),
            ]
        );
    }

    /**
     * 批量流失预测
     */
    public function predictAllChurn(?int $tenantId = null): array
    {
        $query = Customer::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $count = 0;
        $query->chunk(100, function ($customers) use (&$count) {
            foreach ($customers as $customer) {
                $this->predictChurn($customer);
                $count++;
            }
        });

        return ['processed' => $count];
    }

    /**
     * 获取 CRM 仪表盘数据
     */
    public function getDashboardData(int $tenantId): array
    {
        // 分群统计
        $segments = CustomerSegment::where('is_active', true)
            ->orderBy('member_count', 'desc')
            ->get(['id', 'name', 'slug', 'color', 'icon', 'member_count', 'is_dynamic']);

        // RFM 分布
        $rfmDistribution = RfmScore::where('tenant_id', $tenantId)
            ->selectRaw('rfm_segment, count(*) as count')
            ->whereNotNull('rfm_segment')
            ->groupBy('rfm_segment')
            ->pluck('count', 'rfm_segment');

        // 流失风险分布
        $churnDistribution = ChurnPrediction::where('tenant_id', $tenantId)
            ->selectRaw('churn_risk, count(*) as count')
            ->whereNotNull('churn_risk')
            ->groupBy('churn_risk')
            ->pluck('count', 'churn_risk');

        // 高危流失客户（前 10）
        $atRiskCustomers = ChurnPrediction::with('customer.user')
            ->where('tenant_id', $tenantId)
            ->whereIn('churn_risk', ['high', 'critical'])
            ->orderBy('churn_score', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'customer_id' => $p->customer_id,
                'customer_name' => $p->customer?->user?->name ?? 'N/A',
                'churn_score' => $p->churn_score,
                'churn_risk' => $p->churn_risk,
                'predicted_churn_date' => $p->predicted_churn_date?->toDateString(),
                'signals' => $p->signals,
                'recommended_action' => $p->recommended_action,
            ]);

        // 总客户数
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();

        return [
            'segments' => $segments,
            'rfm_distribution' => $rfmDistribution,
            'churn_distribution' => $churnDistribution,
            'at_risk_customers' => $atRiskCustomers,
            'total_customers' => $totalCustomers,
        ];
    }

    // ============ 内部评分方法 ============

    private function scoreRecency(int $days): int
    {
        return match (true) {
            $days <= 7    => 5,
            $days <= 30   => 4,
            $days <= 60   => 3,
            $days <= 90   => 2,
            $days <= 180  => 1,
            default       => 1,
        };
    }

    private function scoreFrequency(int $count): int
    {
        return match (true) {
            $count >= 20 => 5,
            $count >= 10 => 4,
            $count >= 5  => 3,
            $count >= 2  => 2,
            $count >= 1  => 1,
            default      => 1,
        };
    }

    private function scoreMonetary(float $amount): int
    {
        return match (true) {
            $amount >= 50000 => 5,
            $amount >= 10000 => 4,
            $amount >= 5000  => 3,
            $amount >= 1000  => 2,
            $amount >= 0     => 1,
            default          => 1,
        };
    }

    private function getRfmSegment(int $total, int $r, int $f, int $m): string
    {
        return match (true) {
            $total >= 13 && $r >= 4 && $f >= 4 && $m >= 4 => 'Champions',
            $total >= 12 && $r >= 3 && $f >= 3 && $m >= 3 => 'Loyal',
            $r >= 4 && $f >= 1 && $m >= 1                 => 'Recent',
            $r >= 2 && $f >= 3 && $m >= 2                 => 'Frequent',
            $r >= 3 && $m >= 3 && $f <= 2                 => 'Big Spenders',
            $r >= 2 && $f <= 2 && $m >= 2                 => 'Promising',
            $r <= 2 && $f <= 2 && $m >= 2                 => 'Need Attention',
            $r >= 2 && $f <= 1 && $m <= 2                 => 'About to Sleep',
            $r <= 2 && $f <= 2 && $m <= 1                 => 'Lost',
            default                                       => 'Others',
        };
    }

    private function getChurnRiskLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default      => 'low',
        };
    }

    private function getRetentionAction(string $risk, array $signals): string
    {
        if ($risk === 'critical') {
            return '立即联系客户，提供专属优惠或个性化续费方案，安排高级客服跟进';
        }
        if ($risk === 'high') {
            return '发送挽留邮件，提供折扣续费优惠，了解客户需求变化';
        }
        if ($risk === 'medium') {
            return '推送产品更新动态和优质内容，增加互动，提高客户粘性';
        }
        return '保持常规维护，定期推送使用技巧和行业资讯';
    }
}
