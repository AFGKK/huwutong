<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\AuditVisualizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditVisualizationController extends Controller
{
    public function __construct(
        protected AuditVisualizationService $auditVisService
    ) {}

    // ─── 概览仪表�?───

    public function dashboard(Request $request)
    {
        return ApiResponse::success($this->auditVisService->getDashboard($request->user()->tenant_id));
    }

    // ─── 趋势分析 ───

    public function trend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'granularity' => 'nullable|string|in:daily,weekly,monthly',
            'type' => 'nullable|string|in:audit,security,error,system',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->auditVisService->getTrend(
            $request->user()->tenant_id,
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('granularity', 'daily'),
            $request->input('type')
        ));
    }

    // ─── Top 排名 ───

    public function topActions(Request $request)
    {
        return ApiResponse::success($this->auditVisService->getTopActions(
            $request->user()->tenant_id,
            $request->input('start_date', now()->subMonth()->format('Y-m-d')),
            $request->input('end_date', now()->format('Y-m-d')),
            $request->input('limit', 10),
            $request->input('type')
        ));
    }

    public function topUsers(Request $request)
    {
        return ApiResponse::success($this->auditVisService->getTopUsers(
            $request->user()->tenant_id,
            $request->input('start_date', now()->subMonth()->format('Y-m-d')),
            $request->input('end_date', now()->format('Y-m-d')),
            $request->input('limit', 10)
        ));
    }

    public function topIps(Request $request)
    {
        return ApiResponse::success($this->auditVisService->getTopIps(
            $request->user()->tenant_id,
            $request->input('start_date', now()->subMonth()->format('Y-m-d')),
            $request->input('end_date', now()->format('Y-m-d')),
            $request->input('limit', 10)
        ));
    }

    // ─── 分布分析 ───

    public function hourlyDistribution(Request $request)
    {
        return ApiResponse::success($this->auditVisService->getHourlyDistribution(
            $request->user()->tenant_id,
            $request->input('start_date', now()->subMonth()->format('Y-m-d')),
            $request->input('end_date', now()->format('Y-m-d'))
        ));
    }

    public function typeDistribution(Request $request)
    {
        return ApiResponse::success($this->auditVisService->getTypeDistribution(
            $request->user()->tenant_id,
            $request->input('start_date', now()->subMonth()->format('Y-m-d')),
            $request->input('end_date', now()->format('Y-m-d'))
        ));
    }

    public function categoryDistribution(Request $request)
    {
        return ApiResponse::success($this->auditVisService->getCategoryDistribution(
            $request->user()->tenant_id,
            $request->input('start_date', now()->subMonth()->format('Y-m-d')),
            $request->input('end_date', now()->format('Y-m-d'))
        ));
    }

    // ─── 异常检�?───

    public function detectAnomalies(Request $request)
    {
        return ApiResponse::success($this->auditVisService->detectAnomalies($request->user()->tenant_id));
    }

    public function anomalies(Request $request)
    {
        return ApiResponse::success($this->auditVisService->getAnomalies(
            $request->user()->tenant_id,
            $request->only(['severity', 'status', 'type', 'page', 'per_page'])
        ));
    }

    public function updateAnomalyStatus(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:open,acknowledged,resolved,dismissed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->auditVisService->updateAnomalyStatus($id, $request->input('status')));
    }

    // ─── 数据聚合 ───

    public function aggregate(Request $request)
    {
        $count = $this->auditVisService->aggregateDailySummaries(
            $request->user()->tenant_id,
            $request->input('date')
        );

        return ApiResponse::success(['aggregated' => $count, 'date' => $request->input('date', now()->subDay()->format('Y-m-d'))]);
    }
}
