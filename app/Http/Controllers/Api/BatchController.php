<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BatchJob;
use App\Models\BatchJobItem;
use App\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BatchController extends Controller
{
    public function __construct(
        protected BatchService $batchService,
    ) {}

    /**
     * 预览批量操作影响范围
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_model' => 'required|string|in:licenses,subscriptions,customers,invoices,tickets',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'filters' => 'nullable|array',
            'params' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $result = $this->batchService->preview(
            $data['target_model'],
            $data['ids'] ?? null,
            $data['filters'] ?? null,
            $data['params'] ?? [],
        );

        return response()->json(['data' => $result]);
    }

    /**
     * 执行批量操作
     */
    public function execute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:' . implode(',', [
                BatchJob::TYPE_BATCH_ACTIVATE,
                BatchJob::TYPE_BATCH_RENEW,
                BatchJob::TYPE_BATCH_EXPORT,
                BatchJob::TYPE_BATCH_SUSPEND,
                BatchJob::TYPE_BATCH_REVOKE,
                BatchJob::TYPE_BATCH_DELETE,
                BatchJob::TYPE_BATCH_CHANGE_PLAN,
                BatchJob::TYPE_BATCH_CHANGE_STATUS,
            ]),
            'target_model' => 'required|string|in:licenses,subscriptions,customers,invoices,tickets',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'filters' => 'nullable|array',
            'params' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $batchJob = $this->batchService->execute(
            $tenantId,
            $userId,
            $data['type'],
            $data['target_model'],
            $data['ids'] ?? null,
            $data['filters'] ?? null,
            $data['params'] ?? [],
        );

        return response()->json([
            'message' => __('app.api.batch.completed', ['success' => $batchJob->success_count, 'failed' => $batchJob->fail_count]),
            'data' => $batchJob,
        ]);
    }

    /**
     * 查看批量操作列表
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $perPage = (int) $request->input('per_page', 20);

        $jobs = BatchJob::where('tenant_id', $tenantId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($jobs);
    }

    /**
     * 查看单个批量操作详情
     */
    public function show(int $id): JsonResponse
    {
        $job = BatchJob::with(['items' => function ($q) {
            $q->with('targetable')->orderByDesc('created_at');
        }])->findOrFail($id);

        return response()->json(['data' => $job]);
    }

    /**
     * 撤销批量操作
     */
    public function undo(int $id): JsonResponse
    {
        $batchJob = BatchJob::findOrFail($id);

        try {
            $result = $this->batchService->undo($batchJob);
            return response()->json([
                'message' => __('app.api.batch.rollback_done', ['restored' => $result['restored']]),
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * 导出批量操作结果
     */
    public function export(Request $request, int $id): JsonResponse
    {
        $batchJob = BatchJob::findOrFail($id);
        $format = $request->input('format', 'csv');

        try {
            $path = $this->batchService->export($batchJob, $format);
            return response()->json([
                'message' => __('app.api.batch.export_done'),
                'data' => ['path' => $path],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 获取支持的批量操作类型
     */
    public function operationTypes(): JsonResponse
    {
        $types = [
            ['type' => 'batch_activate', 'label' => __('app.api.batch.type_batch_activate'), 'targets' => ['licenses', 'subscriptions']],
            ['type' => 'batch_renew', 'label' => __('app.api.batch.type_batch_renew'), 'targets' => ['licenses', 'subscriptions']],
            ['type' => 'batch_suspend', 'label' => __('app.api.batch.type_batch_suspend'), 'targets' => ['licenses', 'subscriptions']],
            ['type' => 'batch_revoke', 'label' => __('app.api.batch.type_batch_revoke'), 'targets' => ['licenses']],
            ['type' => 'batch_delete', 'label' => __('app.api.batch.type_batch_delete'), 'targets' => ['licenses', 'subscriptions', 'customers', 'invoices', 'tickets']],
            ['type' => 'batch_change_status', 'label' => __('app.api.batch.type_batch_change_status'), 'targets' => ['licenses', 'subscriptions', 'customers', 'invoices', 'tickets']],
            ['type' => 'batch_change_plan', 'label' => __('app.api.batch.type_batch_change_plan'), 'targets' => ['subscriptions']],
            ['type' => 'batch_export', 'label' => __('app.api.batch.type_batch_export'), 'targets' => ['licenses', 'subscriptions', 'customers', 'invoices', 'tickets']],
        ];

        return response()->json(['data' => $types]);
    }
}
