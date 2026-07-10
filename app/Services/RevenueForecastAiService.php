<?php

namespace App\Services;

use App\Models\License;
use App\Models\Subscription;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI 收入预测服务 (M2-43)
 *
 * 基于历史订阅/发票数据，使用 LLM 分析月度/季度 MRR/ARR 趋势，
 * 融合季节性、客户行为等特征生成预测报告。
 */
class RevenueForecastAiService
{
    public function __construct(protected LlmService $llm) {}

    /**
     * 生成收入预测报告
     */
    public function forecast(int $tenantId, array $options = []): array
    {
        $historicalData = $this->collectHistoricalData($tenantId, $options);
        $analysis = $this->analyzeWithLlm($historicalData, $options);

        return [
            'generated_at' => now()->toIso8601String(),
            'period' => $options['period'] ?? 'monthly',
            'horizon' => $options['horizon'] ?? 6, // 预测未来N个月
            'historical_data' => $historicalData,
            'forecast' => $analysis['forecast'] ?? [],
            'insights' => $analysis['insights'] ?? [],
            'recommendations' => $analysis['recommendations'] ?? [],
            'confidence_score' => $analysis['confidence_score'] ?? 0,
            'metadata' => $analysis['metadata'] ?? [],
        ];
    }

    /**
     * 收集历史数据
     */
    protected function collectHistoricalData(int $tenantId, array $options): array
    {
        $months = $options['lookback_months'] ?? 12;

        // 月度订阅收入
        $subscriptionRevenue = Subscription::byTenant($tenantId)
            ->where('created_at', '>=', now()->subMonths($months))
            ->select(
                DB::raw(db_date_format('created_at', '%Y-%m').' as month'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        // 发票数据
        $invoiceRevenue = Invoice::byTenant($tenantId)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths($months))
            ->select(
                DB::raw(db_date_format('created_at', '%Y-%m').' as month'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        // License 统计
        $licenseStats = [
            'total_active' => License::byTenant($tenantId)->where('status', 'active')->count(),
            'total_expired' => License::byTenant($tenantId)->where('status', 'expired')->count(),
            'expiring_next_30d' => License::byTenant($tenantId)
                ->where('status', 'active')
                ->where('expires_at', '<=', now()->addDays(30))
                ->count(),
            'new_last_month' => License::byTenant($tenantId)
                ->where('created_at', '>=', now()->subMonth())
                ->count(),
        ];

        return [
            'tenant_id' => $tenantId,
            'currency' => 'CNY',
            'subscription_revenue_by_month' => $subscriptionRevenue,
            'invoice_revenue_by_month' => $invoiceRevenue,
            'license_stats' => $licenseStats,
            'total_months' => $months,
        ];
    }

    /**
     * 使用 LLM 分析
     */
    protected function analyzeWithLlm(array $data, array $options): array
    {
        $prompt = $this->buildPrompt($data, $options);

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => '你是一个专业的SaaS收入分析师。请根据提供的收入数据，生成收入预测、洞察和建议。必须以JSON格式返回。'],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'model' => $options['model'] ?? null,
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ], 'revenue-forecast');

            $content = $result['content'] ?? '{}';
            $parsed = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        } catch (\Throwable $e) {
            Log::warning('RevenueForecastAi: LLM analysis failed', ['error' => $e->getMessage()]);
        }

        return $this->getFallbackAnalysis($data);
    }

    /**
     * 构建提示
     */
    protected function buildPrompt(array $data, array $options): string
    {
        $horizon = $options['horizon'] ?? 6;
        $period = $options['period'] ?? 'monthly';

        return json_encode([
            'task' => "基于历史数据预测未来{$horizon}个月的{$period}收入",
            'historical_data' => $data,
            'requested_output' => [
                'forecast' => "未来{$horizon}个月的预测（month, predicted_revenue, lower_bound, upper_bound, confidence）",
                'insights' => '关键趋势洞察列表（含季节性、增长率、异常点等）',
                'recommendations' => ' actionable 建议列表',
                'confidence_score' => '整体置信度 0-100',
                'metadata' => ['seasonal_factors', 'growth_rate', 'churn_impact', 'expansion_revenue'],
            ],
            'output_format' => 'strict JSON',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * LLM 不可用时的兜底分析
     */
    protected function getFallbackAnalysis(array $data): array
    {
        $months = $data['subscription_revenue_by_month'] ?? [];
        $total = 0;
        $count = count($months);
        foreach ($months as $m) {
            $total += $m['revenue'] ?? 0;
        }
        $avgMonthly = $count > 0 ? round($total / $count, 2) : 0;

        return [
            'forecast' => array_map(function ($i) use ($avgMonthly) {
                $month = now()->addMonths($i + 1)->format('Y-m');
                return [
                    'month' => $month,
                    'predicted_revenue' => $avgMonthly * (1 + $i * 0.02),
                    'lower_bound' => $avgMonthly * 0.9,
                    'upper_bound' => $avgMonthly * 1.1,
                    'confidence' => max(60 - $i * 5, 30),
                ];
            }, range(0, 5)),
            'insights' => ['基于历史平均值的简单预测', '建议配置 LLM Provider 以获取更准确的 AI 分析'],
            'recommendations' => ['接入大模型以获取深度收入分析'],
            'confidence_score' => 40,
            'metadata' => [
                'seasonal_factors' => [],
                'growth_rate' => 0,
                'churn_impact' => 0,
                'expansion_revenue' => 0,
                'source' => 'fallback',
            ],
        ];
    }
}
