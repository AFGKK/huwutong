<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateLicenseRequest;
use App\Http\Requests\Api\LicenseStatusRequest;
use App\Http\Requests\Api\UpdateLicenseRequest;
use App\Models\License;
use App\Services\KeyGenerator;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService,
        protected KeyGenerator   $keyGenerator,
    ) {}

    /**
     * 获取 License 列表（分页+筛选+排序）
     *
     * GET /api/licenses
     * ?filter[status]=active&filter[type]=standard&sort=-created_at
     */
    public function index(Request $request): JsonResponse
    {
        $query = License::query()->with(['product', 'customer', 'tenant']);

        // 租户隔离
        if ($tenantId = $request->user()->tenant_id) {
            $query->where('tenant_id', $tenantId);
        }

        $paginator = (new class {
            use \App\Http\Concerns\QueryBuilder;
        })->buildPaginatedQuery($query, $request);

        return ApiResponse::paginated($paginator);
    }

    /**
     * License 详情
     *
     * GET /api/licenses/{license}
     */
    public function show(int $id): JsonResponse
    {
        $license = License::with([
            'product', 'customer', 'tenant',
            'devices' => fn($q) => $q->latest(),
            'activations' => fn($q) => $q->latest()->limit(10),
        ])->findOrFail($id);

        // 安全：检查租户归属
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', '无权访问此 License', 403);
        }

        // 分析当前状态
        $statusInfo = $this->licenseService->getStatusInfo($license);

        return ApiResponse::success([
            'license' => $license,
            'status_info' => $statusInfo,
        ]);
    }

    /**
     * 查询 License（通过 license_key）
     *
     * POST /api/licenses/lookup
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string',
        ]);

        $license = License::where('license_key', $data['license_key'])
            ->with(['product', 'customer'])
            ->first();

        if (! $license) {
            return ApiResponse::error('LICENSE_NOT_FOUND', 'License Key 不存在', 404);
        }

        return ApiResponse::success($license);
    }

    /**
     * 生成并创建 License
     *
     * POST /api/licenses
     */
    public function store(CreateLicenseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $licenseKey = $this->keyGenerator->generate($data['type'] ?? 'standard');

        $license = $this->licenseService->create([
            'tenant_id' => $request->user()->tenant_id ?? $data['tenant_id'],
            'product_id' => $data['product_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'license_key' => $licenseKey,
            'type' => $data['type'] ?? 'standard',
            'expires_at' => $data['expires_at'] ?? null,
            'seats' => $data['seats'] ?? 1,
            'max_devices' => $data['max_devices'] ?? 1,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return ApiResponse::created($license, 'License 创建成功');
    }

    /**
     * 批量生成 License
     *
     * POST /api/licenses/batch
     */
    public function batchStore(CreateLicenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $count = min($data['count'] ?? 10, 100);

        $keys = $this->keyGenerator->generateBatch($data['type'] ?? 'standard', $count);

        $licenses = [];
        foreach ($keys as $key) {
            $licenses[] = $this->licenseService->create([
                'tenant_id' => $request->user()->tenant_id ?? $data['tenant_id'],
                'product_id' => $data['product_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'license_key' => $key,
                'type' => $data['type'] ?? 'standard',
                'expires_at' => $data['expires_at'] ?? null,
                'seats' => $data['seats'] ?? 1,
                'max_devices' => $data['max_devices'] ?? 1,
                'metadata' => $data['metadata'] ?? null,
            ]);
        }

        return ApiResponse::created($licenses, "成功创建 {$count} 个 License");
    }

    /**
     * 更新 License 信息
     *
     * PUT /api/licenses/{license}
     */
    public function update(UpdateLicenseRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', '无权操作此 License', 403);
        }

        $updated = $this->licenseService->update($license, $request->validated());

        return ApiResponse::success($updated, 'License 已更新');
    }

    /**
     * 软删除 License（放入回收站）
     *
     * DELETE /api/licenses/{license}
     */
    public function destroy(int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', '无权操作此 License', 403);
        }

        $this->licenseService->softDelete($license);

        return ApiResponse::success(null, 'License 已移至回收站');
    }

    /**
     * 从回收站恢复 License
     *
     * POST /api/licenses/{license}/restore
     */
    public function restoreFromTrash(int $id): JsonResponse
    {
        $license = License::withTrashed()->findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', '无权操作此 License', 403);
        }

        $restored = $this->licenseService->restoreFromTrash($id);

        return ApiResponse::success($restored, 'License 已从回收站恢复');
    }

    /**
     * License 统计
     *
     * GET /api/licenses/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $stats = $this->licenseService->stats($tenantId);

        return ApiResponse::success($stats);
    }

    // ─── 状态管理 ───

    /**
     * 撤销 License
     *
     * POST /api/licenses/{license}/revoke
     */
    public function revoke(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', '无权操作此 License', 403);
        }
        $updated = $this->licenseService->revoke($license, $request->input('reason'));
        return ApiResponse::success($updated, 'License 已撤销');
    }

    /**
     * 挂起 License
     *
     * POST /api/licenses/{license}/suspend
     */
    public function suspend(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', '无权操作此 License', 403);
        }
        $updated = $this->licenseService->suspend($license, $request->input('reason'));
        return ApiResponse::success($updated, 'License 已挂起');
    }

    /**
     * 冻结 License
     *
     * POST /api/licenses/{license}/freeze
     */
    public function freeze(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', '无权操作此 License', 403);
        }
        $updated = $this->licenseService->freeze($license, $request->input('reason'));
        return ApiResponse::success($updated, 'License 已冻结');
    }

    /**
     * 解冻/恢复 License
     *
     * POST /api/licenses/{license}/restore
     */
    public function restore(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', '无权操作此 License', 403);
        }
        $updated = $this->licenseService->restore($license, $request->input('reason'));
        return ApiResponse::success($updated, 'License 已恢复');
    }

    /**
     * 加入黑名单
     *
     * POST /api/licenses/{license}/blacklist
     */
    public function blacklist(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', '无权操作此 License', 403);
        }
        $updated = $this->licenseService->blacklist($license, $request->input('reason'));
        return ApiResponse::success($updated, 'License 已加入黑名单');
    }

    /**
     * 退款处理
     *
     * POST /api/licenses/{license}/refund
     */
    public function refund(LicenseStatusRequest $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        if (! $this->isOwnTenant($license)) {
            return ApiResponse::error('FORBIDDEN', '无权操作此 License', 403);
        }
        $updated = $this->licenseService->refund($license, $request->input('reason'));
        return ApiResponse::success($updated, 'License 已退款');
    }

    /**
     * 检查当前用户是否拥有该资源的租户
     */
    protected function isOwnTenant($model): bool
    {
        $userTenantId = auth()->user()?->tenant_id;
        if (! $userTenantId) {
            return false;
        }

        // 超级管理员可以访问所有租户
        if (auth()->user()?->hasRole('super-admin')) {
            return true;
        }

        return (int) $model->tenant_id === (int) $userTenantId;
    }
}
