<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseDomainWhitelist;
use App\Services\DomainWhitelistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 域名白名单验证控制器 (M2-71)
 */
class DomainWhitelistController extends Controller
{
    public function __construct(
        protected DomainWhitelistService $domainWhitelist,
    ) {
    }

    /**
     * 获取 License 的白名单列表
     */
    public function index(int $licenseId): JsonResponse
    {
        return ApiResponse::success([
            'domains' => $this->domainWhitelist->getWhitelist($licenseId),
        ]);
    }

    /**
     * 添加白名单域名
     */
    public function store(Request $request, int $licenseId): JsonResponse
    {
        $data = $request->validate([
            'domain' => 'required|string|max:255',
            'scope' => 'nullable|string|in:activation,validation,both',
            'notes' => 'nullable|string|max:500',
            'is_admin' => 'boolean',
        ]);

        $license = License::findOrFail($licenseId);
        $userId = $request->user()?->id;

        try {
            $record = $this->domainWhitelist->addDomain(
                $license->id,
                $data['domain'],
                [
                    'scope' => $data['scope'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'is_admin' => $data['is_admin'] ?? false,
                ],
                $userId
            );

            $message = $record->status === 'pending' ? '域名已提交，待审批' : '域名已添加';
            return ApiResponse::success(['domain' => $record], $message);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * 批量添加域名
     */
    public function batchStore(Request $request, int $licenseId): JsonResponse
    {
        $data = $request->validate([
            'domains' => 'required|array|min:1|max:50',
            'domains.*' => 'required|string|max:255',
            'scope' => 'nullable|string|in:activation,validation,both',
            'is_admin' => 'boolean',
        ]);

        $userId = $request->user()?->id;
        $results = $this->domainWhitelist->batchAddDomains(
            $licenseId,
            $data['domains'],
            [
                'scope' => $data['scope'] ?? null,
                'is_admin' => $data['is_admin'] ?? false,
            ],
            $userId
        );

        return ApiResponse::success(['results' => $results]);
    }

    /**
     * 删除白名单域名
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()?->id;
        $this->domainWhitelist->removeDomain($id, $userId);
        return ApiResponse::success(null, '域名已删除');
    }

    /**
     * 验证域名 (供激活/验证流程调用)
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_id' => 'required|integer|exists:licenses,id',
            'domain' => 'required|string|max:255',
            'scope' => 'nullable|string|in:activation,validation',
        ]);

        $result = $this->domainWhitelist->verify(
            $data['license_id'],
            $data['domain'],
            $data['scope'] ?? 'validation'
        );

        return $result['passed']
            ? ApiResponse::success($result, '域名验证通过')
            : ApiResponse::error($result['reason'] ?? '域名不在白名单中', 403, $result);
    }

    /**
     * 获取验证日志
     */
    public function logs(int $licenseId): JsonResponse
    {
        return ApiResponse::success([
            'logs' => $this->domainWhitelist->getLogs($licenseId),
        ]);
    }

    /**
     * 获取统计
     */
    public function stats(int $licenseId): JsonResponse
    {
        return ApiResponse::success(
            $this->domainWhitelist->getStats($licenseId)
        );
    }

    /**
     * 获取待审批列表
     */
    public function pendingApprovals(): JsonResponse
    {
        return ApiResponse::success([
            'pending' => $this->domainWhitelist->getPendingApprovals(),
        ]);
    }

    /**
     * 审批通过
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $adminId = $request->user()?->id;
        $this->domainWhitelist->approveDomain($id, $adminId);
        return ApiResponse::success(null, '已审批通过');
    }

    /**
     * 拒绝审批
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $adminId = $request->user()?->id;
        $this->domainWhitelist->rejectDomain($id, $adminId);
        return ApiResponse::success(null, '已拒绝');
    }
}
