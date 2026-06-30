<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MonthlyRevenueSnapshot;
use App\Models\RevenueRecognitionSchedule;
use App\Services\RevenueRecognitionService;
use App\Services\MrrWaterfallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 收入确认报告（ASC 606 / IFRS 15）（M3-55）
 */
class RevenueRecognitionController extends Controller
{
    public function __construct(
        protected RevenueRecognitionService $revenueService,
        protected MrrWaterfallService $mrrService,
    ) {}

    /**
     * 排程列表
     */
    public function schedules(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = RevenueRecognitionSchedule::forTenant($tenantId)
            ->with(['invoice:id,invoice_no,amount', 'subscription:id,plan'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $schedules = $query->paginate((int) $request->get('per_page', 20));

        // 附加进度
        $schedules->getCollection()->transform(function ($schedule) {
            $schedule->progress = $schedule->progress;
            return $schedule;
        });

        return response()->json([
            'schedules' => $schedules,
            'summary' => $this->revenueService->getSummary($tenantId),
        ]);
    }

    /**
     * 排程详情
     */
    public function showSchedule(int $id): JsonResponse
    {
        $schedule = RevenueRecognitionSchedule::with([
            'lines',
            'invoice:id,invoice_no,amount,paid_at',
            'subscription:id,plan,price,billing_period',
        ])->findOrFail($id);

        $schedule->progress = $schedule->progress;

        return response()->json($schedule);
    }

    /**
     * 执行收入确认
     */
    public function processRecognition(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = $request->date ? \Carbon\Carbon::parse($request->date) : null;
        $result = $this->revenueService->processRecognition($date);

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    /**
     * 汇总统计
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $summary = $this->revenueService->getSummary($tenantId);

        // 递延收入趋势（近12个月）
        $deferredTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $deferred = $this->revenueService->getDeferredRevenue($date, $tenantId);
            $deferredTrend[] = [
                'month' => $date->format('Y-m'),
                'deferred_revenue' => $deferred,
            ];
        }

        $summary['deferred_trend'] = $deferredTrend;

        return response()->json($summary);
    }

    /**
     * 月度快照列表
     */
    public function monthlySnapshots(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $snapshots = MonthlyRevenueSnapshot::where('tenant_id', $tenantId)
            ->orderBy('year_month', 'desc')
            ->paginate((int) $request->get('per_page', 12));

        return response()->json($snapshots);
    }

    /**
     * 手动生成月度快照
     */
    public function generateSnapshot(Request $request): JsonResponse
    {
        $request->validate([
            'year_month' => 'nullable|date_format:Y-m',
        ]);

        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->year_month ?? now()->format('Y-m');

        $snapshot = $this->revenueService->generateMonthlySnapshot($tenantId, $yearMonth);

        return response()->json([
            'success' => true,
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * ASC 606 报告
     */
    public function asc606Report(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|digits:4',
            'month' => 'required|digits:2|between:01,12',
        ]);

        $tenantId = $request->user()->tenant_id;
        $report = $this->revenueService->generateASC606Report(
            $tenantId,
            $request->year,
            $request->month,
        );

        return response()->json($report);
    }

    /**
     * 为已支付发票创建排程（追认）
     */
    public function createSchedules(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_ids' => 'nullable|array',
            'invoice_ids.*' => 'integer|exists:invoices,id',
        ]);

        $tenantId = $request->user()->tenant_id;
        $result = $this->revenueService->createSchedulesForExistingInvoices(
            $tenantId,
            $request->invoice_ids,
        );

        return response()->json($result);
    }

    /**
     * 取消排程（退款/取消订阅时调用）
     */
    public function cancelSchedule(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:200',
        ]);

        try {
            $schedule = $this->revenueService->cancelSchedule(
                $id,
                $request->reason ?? 'manual_cancel',
            );
            return ApiResponse::success($schedule, '排程已取消');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * 重算排程（修复误差重新分摊）
     */
    public function recomputeSchedule(int $id): JsonResponse
    {
        try {
            $schedule = $this->revenueService->recomputeSchedule($id);
            return ApiResponse::success($schedule, '排程已重算');
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * 导出 ASC 606 报告 CSV
     */
    public function exportReport(Request $request): \Illuminate\Http\Response
    {
        $request->validate([
            'year' => 'required|digits:4',
            'month' => 'required|digits:2|between:01,12',
        ]);

        $tenantId = $request->user()->tenant_id;
        $csvContent = $this->revenueService->exportASC606Csv(
            $tenantId,
            $request->year,
            $request->month,
        );

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="asc606-report-' . $request->year . '-' . $request->month . '.csv"',
        ]);
    }

    // ═══════════════ MRR 瀑布图 (M3-59) ═══════════════

    /**
     * MRR瀑布图数据（月环比）
     */
    public function mrrWaterfall(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $months = (int) $request->get('months', 6);

        return ApiResponse::success($this->mrrService->getWaterfall(
            $tenantId,
            $months,
            $request->get('year_month'),
        ));
    }

    public function mrrDrilldown(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        return ApiResponse::success($this->mrrService->getDrilldown(
            $tenantId,
            $yearMonth,
            $request->get('change_type'),
            (int) $request->get('per_page', 20),
        ));
    }

    public function mrrSummary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        return ApiResponse::success($this->mrrService->getSummary($tenantId, $yearMonth));
    }

    public function breakdownByProduct(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        return ApiResponse::success($this->mrrService->getBreakdownByProduct($tenantId, $yearMonth));
    }

    public function breakdownByRegion(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        return ApiResponse::success($this->mrrService->getBreakdownByRegion($tenantId, $yearMonth));
    }

    public function breakdownByCustomerSegment(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        return ApiResponse::success($this->mrrService->getBreakdownByCustomerSegment($tenantId, $yearMonth));
    }
}
