<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerMergeController extends Controller
{
    public function __construct(
        protected CustomerMergeService $customerMergeService,
    ) {}

    /**
     * 合并前预览（不修改数据）
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
            $preview = $this->customerMergeService->previewMerge($source, $target);

            return ApiResponse::success($preview);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * 执行客户合并
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
            $log = $this->customerMergeService->merge(
                $source,
                $target,
                $request->user()->id,
                ['notes' => $request->notes]
            );

            return ApiResponse::success(
                $this->customerMergeService->getMergeDetail($log),
                '客户合并成功'
            );
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
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
            $this->customerMergeService->getMergeHistory($tenantId, $perPage)
        );
    }

    /**
     * 合并详情
     */
    public function detail(Request $request, int $logId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $log = \App\Models\CustomerMergeLog::where('tenant_id', $tenantId)->findOrFail($logId);

        return ApiResponse::success(
            $this->customerMergeService->getMergeDetail($log)
        );
    }

    /**
     * 搜索可合并的客户（用于前端选择器）
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => 'required|string|min:1|max:100',
        ]);

        $tenantId = $request->user()->tenant_id;
        $keyword = $request->keyword;

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('status', '!=', 'merged')
            ->where(function ($q) use ($keyword) {
                $q->where('id', $keyword)
                  ->orWhereHas('user', function ($q) use ($keyword) {
                      $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                  });
            })
            ->with('user:id,name,email')
            ->limit(20)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'user_name' => $c->user?->name ?? '（无用户）',
                'user_email' => $c->user?->email ?? '-',
                'type' => $c->type,
                'level' => $c->level,
                'status' => $c->status,
                'license_count' => $c->licenses()->count(),
            ]);

        return ApiResponse::success($customers);
    }
}
