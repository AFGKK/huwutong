<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChurnPrediction;
use App\Models\Customer;
use App\Models\HealthScore;
use App\Models\HealthScoreHistory;
use App\Services\HealthScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthScoreController extends Controller
{
    public function __construct(
        protected HealthScoreService $healthScoreService,
    ) {}

    /**
     * 健康度看板统计
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $stats = $this->healthScoreService->getDashboardStats($tenantId);

        return response()->json(['data' => $stats]);
    }

    /**
     * 计算/更新单个客户健康分
     */
    public function calculate(Request $request): JsonResponse
    {
        $customerId = $request->input('customer_id');
        $customer = Customer::findOrFail($customerId);

        $healthScore = $this->healthScoreService->calculateForCustomer($customer);

        return response()->json([
            'message' => __('app.controller_compat.health_score_msg_42'),
            'data' => $healthScore,
        ]);
    }

    /**
     * 批量计算所有客户健康分
     */
    public function calculateAll(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $stats = $this->healthScoreService->calculateAll($tenantId);

        return response()->json([
            'message' => "批量计算完成: {$stats['processed']} 成功, {$stats['failed']} 失败",
            'data' => $stats,
        ]);
    }

    /**
     * 获取客户健康分详情
     */
    public function show(int $customerId): JsonResponse
    {
        $healthScore = HealthScore::where('customer_id', $customerId)
            ->latest('calculated_at')
            ->firstOrFail();

        // 加载关联流失预测
        $churn = ChurnPrediction::where('customer_id', $customerId)
            ->latest('predicted_at')
            ->first();

        return response()->json([
            'data' => [
                'health_score' => $healthScore,
                'churn_prediction' => $churn,
            ],
        ]);
    }

    /**
     * 获取客户健康度趋势
     */
    public function trend(Request $request, int $customerId): JsonResponse
    {
        $limit = $request->input('limit', 30);
        $customer = Customer::findOrFail($customerId);

        $history = $this->healthScoreService->getTrend($customer, (int) $limit);

        return response()->json(['data' => $history]);
    }

    /**
     * 获取所有客户健康分（评分排行）
     */
    public function list(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $perPage = (int) $request->input('per_page', 20);
        $grade = $request->input('grade');
        $sortBy = $request->input('sort_by', 'calculated_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $query = HealthScore::where('tenant_id', $tenantId)
            ->whereRaw('calculated_at = (SELECT MAX(calculated_at) FROM health_scores h2 WHERE h2.customer_id = health_scores.customer_id)')
            ->with('customer.user:id,name,email');

        if ($grade && in_array($grade, [HealthScore::GRADE_HEALTHY, HealthScore::GRADE_WARNING, HealthScore::GRADE_CRITICAL])) {
            $query->where('grade', $grade);
        }

        $allowedSorts = ['score', 'calculated_at', 'grade'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $scores = $query->paginate($perPage);

        return response()->json($scores);
    }

    /**
     * 获取流失预警列表
     */
    public function churnList(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $perPage = (int) $request->input('per_page', 20);
        $riskLevel = $request->input('risk_level');

        $query = ChurnPrediction::where('tenant_id', $tenantId)
            ->where('predicted_at', '>=', now()->subDays(1))
            ->with('customer.user:id,name,email')
            ->orderByDesc('churn_probability');

        if ($riskLevel && in_array($riskLevel, [ChurnPrediction::RISK_LOW, ChurnPrediction::RISK_MEDIUM, ChurnPrediction::RISK_HIGH, ChurnPrediction::RISK_CRITICAL])) {
            $query->where('risk_level', $riskLevel);
        }

        $predictions = $query->paginate($perPage);

        return response()->json($predictions);
    }
}
