<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SecretScanLog;
use App\Services\SecretScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 密钥泄露扫描管理 (M1.3-29)
 */
class SecretScanController extends Controller
{
    public function __construct(
        protected SecretScanService $scanService,
    ) {}

    /**
     * 仪表盘统计
     *
     * GET /api/admin/secret-scan/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $total = SecretScanLog::count();
        $open = SecretScanLog::open()->count();
        $critical = SecretScanLog::critical()->count();
        $dismissed = SecretScanLog::where('status', 'dismissed')->count();
        $revoked = SecretScanLog::where('status', 'revoked')->count();

        return ApiResponse::success([
            'total_findings' => $total,
            'open' => $open,
            'critical' => $critical,
            'dismissed' => $dismissed,
            'revoked' => $revoked,
            'service_stats' => $this->scanService->getStats(),
        ]);
    }

    /**
     * 扫描记录列表
     *
     * GET /api/admin/secret-scan/entries
     */
    public function entries(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $search = $request->input('search');
        $severity = $request->input('severity');
        $status = $request->input('status');

        $query = SecretScanLog::with('resolver')->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('file', 'like', "%{$search}%")
                  ->orWhere('pattern_label', 'like', "%{$search}%");
            });
        }
        if ($severity) {
            $query->where('severity', $severity);
        }
        if ($status) {
            $query->where('status', $status);
        }

        return ApiResponse::success($query->paginate($perPage));
    }

    /**
     * 执行一次全量扫描
     *
     * POST /api/admin/secret-scan/scan
     */
    public function scan(): JsonResponse
    {
        $result = $this->scanService->scan();
        $processed = $this->scanService->processLeaks($result);

        return ApiResponse::success([
            'scanned' => $result['scanned'],
            'new_findings' => $processed['processed'],
            'total_findings' => $processed['total'],
        ], __('app.api.secret_scan.scan_done'));
    }

    /**
     * 标记为已处理
     *
     * POST /api/admin/secret-scan/{id}/resolve
     */
    public function resolve(Request $request, SecretScanLog $secretScanLog): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:dismissed,revoked',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(null, $validator->errors()->first(), false, 422);
        }

        $secretScanLog->update([
            'status' => $request->input('action'),
            'note' => $request->input('note'),
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return ApiResponse::success($secretScanLog, __('app.api.secret_scan.processed'));
    }

    /**
     * 执行快速扫描
     *
     * POST /api/admin/secret-scan/quick-scan
     */
    public function quickScan(): JsonResponse
    {
        $result = $this->scanService->quickScan();
        $processed = $this->scanService->processLeaks($result);

        return ApiResponse::success([
            'scanned' => $result['scanned'],
            'new_findings' => $processed['processed'],
            'total_findings' => $processed['total'],
        ], __('app.api.secret_scan.quick_scan_done'));
    }
}
