<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InvoiceReconciliation;
use App\Models\ReconciliationChannelRow;
use App\Models\ReconciliationImport;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function __construct(protected ReconciliationService $service) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->service->dashboard());
    }

    /**
     * 对账记录列表
     */
    public function reconciliations(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->service->listReconciliations(
            $request->only(['status', 'reconciliation_type', 'search']),
            (int) $request->input('per_page', 20),
        ));
    }

    /**
     * 解决对账差异
     */
    public function resolve(Request $request, InvoiceReconciliation $invoiceReconciliation): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => 'required|string|max:200',
            'notes' => 'nullable|string|max:500',
        ]);

        $result = $this->service->resolveReconciliation(
            $invoiceReconciliation->id,
            $validated['resolution'],
            $validated['notes'] ?? null,
        );

        return ApiResponse::success($result, __("app.reconciliation.msg_a606d6a6"));
    }

    // ═══════ CSV 导入 ═══════

    /**
     * CSV 导入列表
     */
    public function imports(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->service->listImports(
            $request->only(['channel', 'status']),
            (int) $request->input('per_page', 20),
        ));
    }

    /**
     * 上传并导入 CSV
     */
    public function importCsv(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'channel' => 'required|in:wechat,alipay,stripe,paypal',
        ]);

        $import = $this->service->importCsv(
            $request->file('file'),
            $validated['channel'],
        );

        return ApiResponse::success($import, __('app.reconciliation.csv') . ($import->status === 'completed' ? __('app.reconciliation.completed') : __("app.reconciliation.msg_5d459d55")));
    }

    // ═══════ 渠道行管理 ═══════

    /**
     * 渠道行列表
     */
    public function channelRows(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->service->listChannelRows(
            $request->only(['import_id', 'channel', 'match_status', 'search']),
            (int) $request->input('per_page', 20),
        ));
    }

    /**
     * 手动匹配渠道行到订单
     */
    public function manualMatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_row_id' => 'required|integer|exists:reconciliation_channel_rows,id',
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        $row = $this->service->manualMatch($validated['channel_row_id'], $validated['order_id']);

        return ApiResponse::success($row, __('app.reconciliation.manual_match') . ($row->match_status === 'matched' ? __('app.reconciliation.success') : __("app.reconciliation.msg_0e6e27a4")));
    }

    // ═══════ 对账日历 ═══════

    /**
     * 对账日历列表
     */
    public function calendars(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->service->listCalendars(
            $request->only(['period_type', 'status']),
            (int) $request->input('per_page', 20),
        ));
    }

    /**
     * 生成日历周期
     */
    public function generateCalendars(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:daily,weekly,monthly,quarterly',
            'months' => 'nullable|integer|min:1|max:12',
        ]);

        $generated = $this->service->generateCalendarPeriods(
            $validated['type'],
            (int) ($validated['months'] ?? 3),
        );

        return ApiResponse::success($generated, __('app.reconciliation.cycles_generated', ['count' => count($generated)]));
    }

    // ═══════ 报告 ═══════

    /**
     * 对账报告
     */
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        return ApiResponse::success($this->service->generateReport(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
        ));
    }
}
