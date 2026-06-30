<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LicenseMergeJob;
use App\Services\LicenseMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseMergeController extends Controller
{
    public function __construct(
        protected LicenseMergeService $service,
    ) {}

    /**
     * 预览合并影响
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'source_customer_id' => 'required|integer|exists:customers,id',
            'target_customer_id' => 'required|integer|exists:customers,id',
        ]);

        $tenantId = $request->user()->tenant_id;

        $source = Customer::where('tenant_id', $tenantId)->findOrFail($request->source_customer_id);
        $target = Customer::where('tenant_id', $tenantId)->findOrFail($request->target_customer_id);

        try {
            $preview = $this->service->previewMerge($source, $target);
            return ApiResponse::success($preview);
        } catch (\Exception $e) {
            return ApiResponse::error('PREVIEW_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 执行 License 合并
     */
    public function merge(Request $request): JsonResponse
    {
        $request->validate([
            'source_customer_id' => 'required|integer|exists:customers,id',
            'target_customer_id' => 'required|integer|exists:customers,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $tenantId = $request->user()->tenant_id;

        $source = Customer::where('tenant_id', $tenantId)->findOrFail($request->source_customer_id);
        $target = Customer::where('tenant_id', $tenantId)->findOrFail($request->target_customer_id);

        try {
            $job = $this->service->merge(
                $source,
                $target,
                $request->user()->id,
                ['notes' => $request->notes]
            );

            return ApiResponse::success(
                $this->service->getMergeDetail($job),
                'License 合并成功'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('MERGE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 合并历史记录
     */
    public function history(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $perPage = (int) $request->get('per_page', 20);

        return ApiResponse::success(
            $this->service->getMergeHistory($tenantId, $perPage)
        );
    }

    /**
     * 合并详情
     */
    public function detail(LicenseMergeJob $job): JsonResponse
    {
        $this->authorizeTenant($job);
        return ApiResponse::success(
            $this->service->getMergeDetail($job)
        );
    }

    /**
     * 回滚合并
     */
    public function rollback(LicenseMergeJob $job): JsonResponse
    {
        $this->authorizeTenant($job);

        try {
            $this->service->rollback($job);
            return ApiResponse::success(
                $this->service->getMergeDetail($job),
                'License 合并已回滚'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('ROLLBACK_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 搜索客户
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => 'required|string|min:1|max:100',
        ]);

        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success(
            $this->service->searchCustomers($tenantId, $request->keyword)
        );
    }

    /**
     * 验证租户权限
     */
    protected function authorizeTenant(LicenseMergeJob $job): void
    {
        if ($job->tenant_id !== auth()->user()->tenant_id) {
            abort(403, '无权访问此合并记录');
        }
    }
}
