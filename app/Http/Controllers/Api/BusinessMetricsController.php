<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BusinessMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 业务指标看板 (M2-121)
 *
 * 与技术指标分离的纯业务看板：MRR/ARR/Churn Rate/LTV/CAC/激活转化率/续费率 + 同比环比趋势
 */
class BusinessMetricsController extends Controller
{
    public function __construct(
        protected BusinessMetricsService $metrics,
    ) {}

    /**
     * 业务指标看板总览
     */
    public function overview(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->metrics->overview(),
        ]);
    }

    /**
     * MRR 月度趋势（同比环比）
     */
    public function mrrTrend(Request $request): JsonResponse
    {
        $months = min((int) $request->input('months', 12), 36);
        return response()->json([
            'success' => true,
            'data' => $this->metrics->mrrTrend($months),
        ]);
    }

    /**
     * 关键指标趋势
     */
    public function metricTrends(Request $request): JsonResponse
    {
        $months = min((int) $request->input('months', 12), 36);
        return response()->json([
            'success' => true,
            'data' => $this->metrics->metricTrends($months),
        ]);
    }

    /**
     * 流失率趋势
     */
    public function churnTrend(Request $request): JsonResponse
    {
        $months = min((int) $request->input('months', 12), 36);
        return response()->json([
            'success' => true,
            'data' => $this->metrics->churnRateTrend($months),
        ]);
    }

    /**
     * 同期群分析
     */
    public function cohortAnalysis(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->metrics->cohortAnalysis(),
        ]);
    }

    /**
     * 报表导出
     */
    public function export(Request $request): JsonResponse
    {
        $format = $request->input('format', 'csv');
        $months = min((int) $request->input('months', 12), 60);
        $data = $this->metrics->exportData($format, $months);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 完整看板（一次性返回所有数据）
     */
    public function dashboard(): JsonResponse
    {
        $overview = $this->metrics->overview();
        $trends = $this->metrics->metricTrends(12);
        $cohorts = $this->metrics->cohortAnalysis();

        $healthScore = $this->calculateHealthScore($overview);

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => $overview,
                'trends' => $trends,
                'cohorts' => $cohorts,
                'health_score' => $healthScore,
            ],
        ]);
    }

    /**
     * 业务健康评分
     */
    protected function calculateHealthScore(array $overview): array
    {
        $score = 100;

        // 流失率扣分
        $churnRate = $overview['churn_rate'] ?? 0;
        if ($churnRate > 10) $score -= 20;
        elseif ($churnRate > 5) $score -= 10;

        // LTV/CAC 扣分
        $ratio = $overview['ltv_cac_ratio'] ?? 0;
        if ($ratio < 1) $score -= 20;
        elseif ($ratio < 3) $score -= 10;

        // 续费率扣分
        $renewalRate = $overview['renewal_rate'] ?? 0;
        if ($renewalRate < 60) $score -= 15;
        elseif ($renewalRate < 80) $score -= 8;

        // 激活率扣分
        $activationRate = $overview['activation_rate'] ?? 0;
        if ($activationRate < 50) $score -= 10;
        elseif ($activationRate < 70) $score -= 5;

        // 试用转化率扣分
        $trialConv = $overview['trial_conversion_rate'] ?? 0;
        if ($trialConv < 20) $score -= 10;
        elseif ($trialConv < 30) $score -= 5;

        $score = max(0, min(100, $score));

        $level = $score >= 80 ? 'healthy' : ($score >= 50 ? 'warning' : 'critical');

        return [
            'score' => $score,
            'level' => $level,
            'label' => $level === 'healthy' ? '健康' : ($level === 'warning' ? '注意' : '危险'),
        ];
    }
}
