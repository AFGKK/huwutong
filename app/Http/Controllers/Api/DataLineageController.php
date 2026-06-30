<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\DataLineageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 数据血缘追踪 (M2-113)
 */
class DataLineageController extends Controller
{
    public function __construct(protected DataLineageService $lineageService) {}

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->lineageService->dashboard($request->user()->tenant_id)
        );
    }

    /**
     * 查询血缘记录列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->lineageService->queryRecords($request->user()->tenant_id, $request->all())
        );
    }

    /**
     * 获取特定对象的血缘链路
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'trackable_type' => 'required|string|max:120',
            'trackable_id' => 'required|string|max:64',
        ]);

        return ApiResponse::success(
            $this->lineageService->getLineage(
                $request->input('trackable_type'),
                $request->input('trackable_id'),
                $request->all()
            )
        );
    }

    /**
     * 获取单条血缘记录的完整链路（含祖先/后代）
     */
    public function chain(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(
            $this->lineageService->getLineageChain($id, $request->user()->tenant_id)
        );
    }

    /**
     * 手动记录一条血缘事件
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trackable_type' => 'required|string|max:120',
            'trackable_id' => 'required|string|max:64',
            'trackable_label' => 'nullable|string|max:255',
            'data_category' => 'required|string|in:' . implode(',', array_keys(\App\Models\DataLineageRecord::DATA_CATEGORIES)),
            'sensitivity' => 'nullable|string|in:' . implode(',', array_keys(\App\Models\DataLineageRecord::SENSITIVITY_LEVELS)),
            'event_type' => 'required|string|in:' . implode(',', array_keys(\App\Models\DataLineageRecord::EVENT_TYPES)),
            'event_label' => 'nullable|string|max:255',
            'source_system' => 'nullable|string|max:60',
            'target_system' => 'nullable|string|max:60',
            'changes' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        $record = $this->lineageService->record(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id,
        ]));

        return ApiResponse::created($record);
    }

    /**
     * 获取可追踪对象列表（聚合）
     */
    public function trackedObjects(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->lineageService->getTrackedObjects($request->user()->tenant_id, $request->all())
        );
    }

    /**
     * 导出血缘记录（CSV）
     */
    public function export(Request $request): \Illuminate\Http\Response
    {
        $records = \App\Models\DataLineageRecord::byTenant($request->user()->tenant_id)
            ->with('actor:id,name,email')
            ->orderBy('recorded_at', 'desc')
            ->limit(5000)
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="data-lineage-' . now()->format('Ymd') . '.csv"',
        ];

        $callback = function () use ($records) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                '时间', '对象类型', '对象ID', '对象标签', '数据类别', '敏感度',
                '事件类型', '事件描述', '来源系统', '目标系统', '操作人', '链路ID',
            ]);
            foreach ($records as $r) {
                fputcsv($handle, [
                    $r->recorded_at?->toIso8601String(),
                    $r->trackable_type,
                    $r->trackable_id,
                    $r->trackable_label,
                    $r->data_category,
                    $r->sensitivity,
                    $r->event_type,
                    $r->event_label,
                    $r->source_system,
                    $r->target_system ?? '',
                    $r->actor?->name ?? $r->actor?->email ?? ($r->actor_type ?? 'system'),
                    $r->trace_id,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
