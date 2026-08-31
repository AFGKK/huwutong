<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\UsageQuota;
use App\Services\UsageMeterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UsageMeterController extends Controller
{
    public function __construct(
        protected UsageMeterService $usageMeter,
    ) {}

    /**
     * 获取可用计量指标列表
     */
    public function metrics(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->usageMeter->getAvailableMetrics(),
        ]);
    }

    /**
     * 记录用量（供内部系统调用）
     */
    public function record(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'metric_key'  => 'required|string|max:100',
            'action'      => 'required|string|max:100',
            'license_id'  => 'nullable|exists:licenses,id',
            'customer_id' => 'nullable|exists:customers,id',
            'quantity'    => 'nullable|integer|min:1|max:1000000',
            'unit'        => 'nullable|string|max:30',
            'context'     => 'nullable|array',
            'recorded_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $record = $this->usageMeter->record(array_merge(
            $request->only(['metric_key', 'action', 'license_id', 'customer_id', 'quantity', 'unit', 'context']),
            [
                'tenant_id'   => $request->user()->tenant_id,
                'recorded_at' => $request->date('recorded_at'),
            ]
        ));

        return response()->json([
            'success' => true,
            'data'    => $record,
            'message' => __('app.controller_compat.usage_meter_msg_61'),
        ], 201);
    }

    /**
     * 批量记录用量
     */
    public function recordBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'records'           => 'required|array|min:1|max:1000',
            'records.*.metric_key'  => 'required|string|max:100',
            'records.*.action'      => 'required|string|max:100',
            'records.*.license_id'  => 'nullable|exists:licenses,id',
            'records.*.quantity'    => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $tenantId = $request->user()->tenant_id;
        $records = collect($request->input('records'))->map(fn ($r) => array_merge($r, [
            'tenant_id' => $tenantId,
        ]))->toArray();

        $count = $this->usageMeter->recordBatch($records);

        return response()->json([
            'success' => true,
            'data'    => ['recorded_count' => $count],
            'message' => "成功记录 {$count} 条用量",
        ]);
    }

    /**
     * 检查某个 License 的配额
     */
    public function checkQuota(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_id' => 'required|exists:licenses,id',
            'metric_key' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $license = License::findOrFail($request->input('license_id'));
        $this->authorize('view', $license);

        $result = $this->usageMeter->checkQuota($license, $request->input('metric_key'));

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * 获取用量统计数据
     */
    public function stats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'metric_key' => 'required|string|max:100',
            'period'     => 'nullable|in:daily,monthly',
            'limit'      => 'nullable|integer|min:1|max:60',
            'license_id' => 'nullable|exists:licenses,id',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $tenantId = $request->user()->tenant_id;
        $metricKey = $request->input('metric_key');
        $period = $request->input('period', 'monthly');
        $limit = (int) $request->input('limit', 12);

        $stats = $this->usageMeter->getStats($tenantId, $metricKey, $period, $limit);

        // 如果指定了 license_id/customer_id，补充当前窗口用量
        $currentWindow = null;
        if ($request->has('license_id') || $request->has('customer_id')) {
            $currentWindow = $this->usageMeter->getUsageInWindow(
                $tenantId,
                $metricKey,
                $period === 'daily' ? 'daily' : 'monthly',
                $request->input('license_id'),
                $request->input('customer_id'),
            );
        }

        return response()->json([
            'success' => true,
            'data'    => $stats,
            'meta'    => $currentWindow !== null ? ['current_window_usage' => $currentWindow] : null,
        ]);
    }

    /**
     * 获取当前时间窗用量（便捷端点）
     */
    public function currentUsage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'metric_key'  => 'required|string|max:100',
            'window_type' => 'nullable|in:total,daily,monthly,custom',
            'license_id'  => 'nullable|exists:licenses,id',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $tenantId = $request->user()->tenant_id;
        $quantity = $this->usageMeter->getUsageInWindow(
            $tenantId,
            $request->input('metric_key'),
            $request->input('window_type', 'monthly'),
            $request->input('license_id'),
            null,
            $request->date('start_date'),
            $request->date('end_date'),
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'metric_key'  => $request->input('metric_key'),
                'window_type' => $request->input('window_type', 'monthly'),
                'quantity'    => $quantity,
            ],
        ]);
    }

    /**
     * 用量配额管理 — 列表
     */
    public function quotas(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $quotas = UsageQuota::where('tenant_id', $tenantId)
            ->with(['license:id,license_key,name', 'product:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $quotas,
        ]);
    }

    /**
     * 用量配额管理 — 创建/更新
     */
    public function upsertQuota(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'metric_key'      => 'required|string|max:100',
            'window_type'     => 'required|in:total,daily,monthly',
            'quota_limit'     => 'required|integer|min:1',
            'license_id'      => 'nullable|exists:licenses,id',
            'product_id'      => 'nullable|exists:products,id',
            'action_on_exceed' => 'nullable|in:block,warn,log',
            'is_active'       => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $tenantId = $request->user()->tenant_id;

        $quota = $this->usageMeter->upsertQuota(array_merge(
            $request->only(['metric_key', 'window_type', 'quota_limit', 'license_id', 'product_id', 'action_on_exceed', 'is_active']),
            ['tenant_id' => $tenantId]
        ));

        return response()->json([
            'success' => true,
            'data'    => $quota->load(['license:id,license_key,name', 'product:id,name']),
            'message' => __('app.controller_compat.usage_meter_msg_249'),
        ]);
    }

    /**
     * 用量配额管理 — 删除
     */
    public function deleteQuota(int $id): JsonResponse
    {
        $quota = UsageQuota::findOrFail($id);
        $this->authorize('delete', $quota);

        $quota->delete();

        return response()->json([
            'success' => true,
            'message' => __('app.controller_compat.usage_meter_msg_265'),
        ]);
    }

    /**
     * 用量总览仪表盘
     */
    public function overview(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        // 本月各指标用量汇总
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $records = UsageRecord::where('tenant_id', $tenantId)
            ->whereBetween('recorded_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('metric_key, SUM(quantity) as total, COUNT(*) as count')
            ->groupBy('metric_key')
            ->get();

        // 各指标配额
        $quotas = UsageQuota::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->groupBy('metric_key');

        $metrics = $records->map(function ($record) use ($quotas) {
            $quota = $quotas->get($record->metric_key)?->first();

            return [
                'metric_key' => $record->metric_key,
                'name'       => UsageMeterService::METRICS[$record->metric_key]['name'] ?? $record->metric_key,
                'total'      => (int) $record->total,
                'count'      => (int) $record->count,
                'quota_limit' => $quota?->quota_limit,
                'usage_rate'  => $quota && $quota->quota_limit > 0
                    ? round(((int) $record->total / $quota->quota_limit) * 100, 1)
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'period' => [
                    'start' => $startOfMonth->toDateString(),
                    'end'   => $endOfMonth->toDateString(),
                ],
                'metrics' => $metrics->values(),
                'total_metrics' => count(UsageMeterService::METRICS),
                'active_quotas' => $quotas->count(),
            ],
        ]);
    }
}
