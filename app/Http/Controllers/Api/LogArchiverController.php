<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditArchivePolicy;
use App\Models\AuditArchiveRecord;
use App\Services\LogArchiverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-73 审计日志归档至低成本存储 API
 */
class LogArchiverController extends Controller
{
    public function __construct(
        private readonly LogArchiverService $logArchiver,
    ) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->logArchiver->getDashboard(),
        ]);
    }

    /**
     * 存储层级配置
     */
    public function tiers(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->logArchiver->getTierConfig(),
        ]);
    }

    /**
     * 归档统计
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->logArchiver->getArchiveStats(),
        ]);
    }

    /**
     * 归档策略列表
     */
    public function policies(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->logArchiver->getPolicies(),
        ]);
    }

    /**
     * 创建/更新归档策略
     */
    public function upsertPolicy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:audit_archive_policies,id',
            'type' => 'required|string|in:audit,security,error,system',
            'name' => 'nullable|string|max:100',
            'archive_after_days' => 'nullable|integer|min:1|max:3650',
            'delete_after_days' => 'nullable|integer|min:1|max:7300',
            'archive_disk' => 'nullable|string|max:50',
            'storage_tier' => 'nullable|string|in:hot,warm,cold,frozen',
            'compress_archive' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        $policy = $this->logArchiver->upsertPolicy($validated);

        return response()->json([
            'code' => 0,
            'message' => __('app.controller_compat.log_archiver_msg_87'),
            'data' => $policy,
        ]);
    }

    /**
     * 执行归档
     */
    public function archive(int $id): JsonResponse
    {
        $policy = AuditArchivePolicy::findOrFail($id);
        $record = $this->logArchiver->archive($policy);

        return response()->json([
            'code' => 0,
            'message' => $record->status === 'completed' ? '归档完成' : '归档失败',
            'data' => $record,
        ]);
    }

    /**
     * 归档记录列表
     */
    public function records(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'status', 'storage_class']);

        return response()->json([
            'code' => 0,
            'data' => $this->logArchiver->getRecords($filters, $request->input('per_page', 20)),
        ]);
    }

    /**
     * 取回归档
     */
    public function requestRestore(Request $request, int $id): JsonResponse
    {
        $record = AuditArchiveRecord::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $restoreRequest = $this->logArchiver->requestRestore(
                $record,
                $validated['reason'],
                $request->user()?->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['code' => 1, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'code' => 0,
            'message' => __('app.controller_compat.log_archiver_msg_143'),
            'data' => $restoreRequest,
        ]);
    }

    /**
     * 执行取回
     */
    public function executeRestore(int $id): JsonResponse
    {
        $restoreRequest = \App\Models\AuditArchiveRestoreRequest::findOrFail($id);
        $restoreRequest = $this->logArchiver->executeRestore($restoreRequest);

        return response()->json([
            'code' => 0,
            'message' => $restoreRequest->status === 'available' ? '取回完成' : '取回失败',
            'data' => $restoreRequest,
        ]);
    }

    /**
     * 取回请求列表
     */
    public function restoreRequests(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'archive_record_id']);

        return response()->json([
            'code' => 0,
            'data' => $this->logArchiver->getRestoreRequests($filters, $request->input('per_page', 20)),
        ]);
    }

    /**
     * 取消取回请求
     */
    public function cancelRestore(int $id): JsonResponse
    {
        $result = $this->logArchiver->cancelRestoreRequest($id);

        if (!$result) {
            return response()->json(['code' => 1, 'message' => __('app.controller_compat.log_archiver_msg_184')], 422);
        }

        return response()->json([
            'code' => 0,
            'message' => __('app.controller_compat.log_archiver_msg_189'),
            'data' => $result,
        ]);
    }

    /**
     * 处理过期请求
     */
    public function processExpired(): JsonResponse
    {
        $count = $this->logArchiver->processExpiredRequests();

        return response()->json([
            'code' => 0,
            'message' => "已处理 {$count} 个过期请求",
            'data' => ['processed' => $count],
        ]);
    }
}
