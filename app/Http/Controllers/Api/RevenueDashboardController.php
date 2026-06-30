<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RevenueDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 平台收益总览 & 渠道 ROI API
 *
 * M3-73 面向管理员的收益分析仪表盘
 */
class RevenueDashboardController extends Controller
{
    public function __construct(
        protected RevenueDashboardService $dashboard,
    ) {}

    /**
     * 平台收益总览
     */
    public function overview(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboard->platformOverview(),
        ]);
    }

    /**
     * 渠道 ROI 分析
     */
    public function channelRoi(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboard->channelRoi(),
        ]);
    }

    /**
     * 渠道月度趋势
     */
    public function channelTrend(Request $request): JsonResponse
    {
        $months = min((int) $request->input('months', 12), 36);

        return response()->json([
            'success' => true,
            'data' => $this->dashboard->channelTrend($months),
        ]);
    }

    /**
     * 渠道质量分析
     */
    public function channelQuality(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboard->channelQuality(),
        ]);
    }

    /**
     * 月度收益趋势
     */
    public function revenueTrend(Request $request): JsonResponse
    {
        $months = min((int) $request->input('months', 24), 60);

        return response()->json([
            'success' => true,
            'data' => $this->dashboard->revenueTrend($months),
        ]);
    }

    /**
     * 支付方式分布
     */
    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboard->paymentMethodDistribution(),
        ]);
    }

    /**
     * 代理层级收益分布
     */
    public function agentLevels(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboard->agentLevelDistribution(),
        ]);
    }

    /**
     * 代理商收益排行榜
     *
     * GET /admin/revenue/agent-leaderboard
     */
    public function agentLeaderboard(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 20), 100);
        return response()->json([
            'success' => true,
            'data' => $this->dashboard->agentLeaderboard($limit),
        ]);
    }

    /**
     * 月度结算报表
     *
     * GET /admin/revenue/monthly-report
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $yearMonth = $request->input('year_month', now()->format('Y-m'));
        return response()->json([
            'success' => true,
            'data' => $this->dashboard->monthlySettlementReport($yearMonth),
        ]);
    }

    /**
     * 综合收益看板（一次返回所有数据）
     */
    public function dashboard(): JsonResponse
    {
        $overview = $this->dashboard->platformOverview();
        $channelRoi = $this->dashboard->channelRoi();
        $channelTrend = $this->dashboard->channelTrend(12);
        $quality = $this->dashboard->channelQuality();
        $paymentMethods = $this->dashboard->paymentMethodDistribution();
        $agentLevels = $this->dashboard->agentLevelDistribution();
        $revenueTrend = $this->dashboard->revenueTrend(24);

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => $overview,
                'channel_roi' => $channelRoi,
                'channel_trend' => $channelTrend,
                'channel_quality' => $quality,
                'payment_methods' => $paymentMethods,
                'agent_levels' => $agentLevels,
                'revenue_trend' => $revenueTrend,
            ],
        ]);
    }
}
