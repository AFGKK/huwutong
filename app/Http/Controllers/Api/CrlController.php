<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CRL 吊销列表管理 (M1.3-03)
 */
class CrlController extends Controller
{
    public function __construct(
        protected CrlService $crlService,
    ) {}

    /**
     * CRL 仪表盘统计
     *
     * GET /api/admin/crl/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->crlService->getStats());
    }

    /**
     * 获取 CRL 列表
     *
     * GET /api/admin/crl/entries
     */
    public function entries(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $search = $request->input('search');

        $query = \App\Models\OfflineCrlEntry::with('certificate')->orderByDesc('created_at');

        if ($search) {
            $query->where('license_key', 'like', "%{$search}%");
        }

        return ApiResponse::success($query->paginate($perPage));
    }

    /**
     * 吊销 License
     *
     * POST /api/admin/crl/revoke
     */
    public function revoke(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'license_key' => 'required|string|max:100',
            'reason' => 'nullable|string|max:500',
        ])->validate();

        try {
            $this->crlService->revoke($validated['license_key'], $validated['reason'] ?? '管理员吊销');
            return ApiResponse::success(null, 'License 已加入吊销列表');
        } catch (\RuntimeException $e) {
            return ApiResponse::success(null, $e->getMessage(), false, 400);
        }
    }

    /**
     * 批量吊销
     *
     * POST /api/admin/crl/batch-revoke
     */
    public function batchRevoke(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'license_keys' => 'required|array|min:1|max:100',
            'license_keys.*' => 'string|max:100',
            'reason' => 'nullable|string|max:500',
        ])->validate();

        $results = $this->crlService->batchRevoke(
            $validated['license_keys'],
            $validated['reason'] ?? '批量吊销'
        );

        return ApiResponse::success($results, "成功吊销 {$results['revoked']} 个 License");
    }

    /**
     * 恢复 License（从 CRL 移除）
     *
     * POST /api/admin/crl/restore
     */
    public function restore(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'license_key' => 'required|string|max:100',
        ])->validate();

        $this->crlService->restore($validated['license_key']);
        return ApiResponse::success(null, 'License 已移出吊销列表');
    }

    /**
     * 检查 License 吊销状态
     *
     * GET /api/admin/crl/check/{licenseKey}
     */
    public function check(string $licenseKey): JsonResponse
    {
        $info = $this->crlService->getRevocationInfo($licenseKey);
        if ($info) {
            return ApiResponse::success($info, 'License 已被吊销');
        }
        return ApiResponse::success(null, 'License 未被吊销');
    }

    /**
     * 网络恢复自动补全验证
     *
     * POST /api/admin/crl/auto-verify
     */
    public function autoVerify(Request $request): JsonResponse
    {
        $batchSize = min((int) $request->input('batch', 100), 500);
        $result = $this->crlService->autoCompleteVerification($batchSize);
        return ApiResponse::success($result, "已处理 {$result['processed']} 条离线记录");
    }
}
