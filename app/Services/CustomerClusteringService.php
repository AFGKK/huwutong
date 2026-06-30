<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerClusterAssignment;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Support\Facades\DB;

/**
 * M3-37 AI 客户行为聚类
 *
 * 无监督学习自动分层客户群→精细化运营策略+差异化服务+精准营销
 */
class CustomerClusteringService
{
    protected array $segmentKeys = [
        'high_value_active', 'growth_potential', 'at_risk', 'new_onboarding', 'low_engagement',
    ];

    /**
     * 执行聚类分析
     */
    public function runClustering(int $tenantId): array
    {
        $customers = Customer::where('tenant_id', $tenantId)->get();
        $results = ['total' => $customers->count(), 'assigned' => 0];

        foreach ($customers as $customer) {
            $features = $this->extractFeatures($customer);
            $segmentKey = $this->classifyCustomer($features);
            $score = $this->calculateScore($features, $segmentKey);

            // 记录旧的segment
            $oldAssignment = CustomerClusterAssignment::where('customer_id', $customer->id)
                ->latest()->first();

            CustomerClusterAssignment::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'segment_key' => $segmentKey,
                'score' => $score,
                'features' => $features,
                'assigned_at' => now(),
                'previous_segment_at' => $oldAssignment?->assigned_at,
            ]);

            $results['assigned']++;
        }

        return $results;
    }

    /**
     * 提取客户特征向量
     */
    public function extractFeatures(Customer $customer): array
    {
        $ninetyDaysAgo = now()->subDays(90);

        $licenseCount = License::where('customer_id', $customer->id)->count();
        $activeLicenses = License::where('customer_id', $customer->id)->whereIn('status', ['active', 'trial'])->count();
        $deviceCount = Device::whereIn('license_id', License::where('customer_id', $customer->id)->select('id'))->count();

        $activations = LicenseActivation::whereIn('license_id', License::where('customer_id', $customer->id)->select('id'))
            ->where('activated_at', '>=', $ninetyDaysAgo)->count();

        $totalSpend = Invoice::where('customer_id', $customer->id)->where('status', 'paid')->sum('amount');
        $avgOrderValue = Invoice::where('customer_id', $customer->id)->where('status', 'paid')->avg('amount') ?? 0;

        $firstLicense = License::where('customer_id', $customer->id)->orderBy('created_at')->first();
        $daysSinceSignup = $customer->created_at->diffInDays(now());
        $subscriptionMonths = $firstLicense ? max(1, $firstLicense->created_at->diffInMonths(now())) : 0;

        $activationRate = $licenseCount > 0 ? round(($activeLicenses / $licenseCount) * 100, 2) : 0;
        $devicesPerLicense = $activeLicenses > 0 ? round($deviceCount / $activeLicenses, 2) : 0;

        return compact(
            'licenseCount', 'activeLicenses', 'deviceCount', 'activations',
            'totalSpend', 'avgOrderValue', 'daysSinceSignup',
            'subscriptionMonths', 'activationRate', 'devicesPerLicense'
        );
    }

    /**
     * 基于特征向量分类客户
     */
    protected function classifyCustomer(array $features): string
    {
        // 规则引擎实现 K-Means 近似分类
        $score = $this->computeCompositeScore($features);

        // 新客户 (30天内)
        if ($features['daysSinceSignup'] <= 30 && $features['licenseCount'] <= 2) {
            return 'new_onboarding';
        }

        // 高价值活跃 (高消费 + 高活跃 + 多License)
        if ($features['totalSpend'] >= 10000 && $features['activationRate'] >= 60 && $features['licenseCount'] >= 3) {
            return 'high_value_active';
        }

        // 流失风险 (低活跃 + License在减少或到期)
        if ($features['activationRate'] < 30 && $features['subscriptionMonths'] > 3) {
            return 'at_risk';
        }

        // 成长潜力 (中等消费 + 较高活跃 + 扩展空间)
        if ($features['totalSpend'] >= 1000 && $features['activationRate'] >= 50) {
            return 'growth_potential';
        }

        // 基于综合评分进一步细分
        if ($score >= 70) return 'high_value_active';
        if ($score >= 50) return 'growth_potential';
        if ($score >= 30) return 'at_risk';

        return 'low_engagement';
    }

    /**
     * 计算综合评分 (0-100)
     */
    protected function computeCompositeScore(array $features): float
    {
        $score = 0;

        // License数量: 每1个+5分, 上限25
        $score += min($features['licenseCount'] * 5, 25);
        // 总消费: 每1000元+5分, 上限25
        $score += min(($features['totalSpend'] / 1000) * 5, 25);
        // 激活率: 每10%+2分, 上限20
        $score += min(($features['activationRate'] / 10) * 2, 20);
        // 活跃天数: 每30天+3分, 上限15
        $score += min(($features['daysSinceSignup'] / 30) * 3, 15);
        // 设备数: 每台+2分, 上限15
        $score += min($features['deviceCount'] * 2, 15);

        return min($score, 100);
    }

    /**
     * 计算客户对分段的归属分数
     */
    protected function calculateScore(array $features, string $segmentKey): float
    {
        $composite = $this->computeCompositeScore($features);

        return match ($segmentKey) {
            'high_value_active' => min($composite, 100),
            'growth_potential' => min(max($composite - 10, 0), 100),
            'at_risk' => max(100 - $composite, 0),
            'new_onboarding' => min(30 + ($features['activationRate'] ?? 0) * 0.3, 70),
            'low_engagement' => max(50 - $composite, 0),
            default => 50,
        };
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $segments = config('customer-clustering.segments', []);
        $stats = [];

        foreach ($segments as $key => $config) {
            $count = CustomerClusterAssignment::where('tenant_id', $tenantId)
                ->where('segment_key', $key)
                ->distinct('customer_id')
                ->count('customer_id');

            $avgScore = CustomerClusterAssignment::where('tenant_id', $tenantId)
                ->where('segment_key', $key)
                ->avg('score');

            $stats[$key] = [
                'label' => $config['label'],
                'color' => $config['color'],
                'count' => $count,
                'avg_score' => round($avgScore ?? 0, 2),
                'actions' => config("customer-clustering.actions.{$key}", []),
            ];
        }

        $totalAssigned = CustomerClusterAssignment::where('tenant_id', $tenantId)
            ->distinct('customer_id')->count('customer_id');

        $recentChanges = CustomerClusterAssignment::where('tenant_id', $tenantId)
            ->whereNotNull('previous_segment_at')
            ->with('customer')
            ->latest()
            ->limit(20)
            ->get();

        return [
            'segments' => $stats,
            'total_assigned' => $totalAssigned,
            'recent_changes' => $recentChanges->toArray(),
            'segment_config' => $segments,
        ];
    }

    /**
     * 获取客户聚类详情
     */
    public function getCustomerCluster(int $customerId): ?array
    {
        $assignment = CustomerClusterAssignment::where('customer_id', $customerId)
            ->latest()
            ->first();

        if (!$assignment) return null;

        $customer = Customer::find($customerId);
        $features = $this->extractFeatures($customer);

        return [
            'customer' => $customer->toArray(),
            'current_segment' => $assignment->segment_key,
            'segment_label' => config("customer-clustering.segments.{$assignment->segment_key}.label", $assignment->segment_key),
            'score' => $assignment->score,
            'features' => $features,
            'assigned_at' => $assignment->assigned_at,
            'previous_segment_at' => $assignment->previous_segment_at,
            'recommended_actions' => config("customer-clustering.actions.{$assignment->segment_key}", []),
        ];
    }
}
