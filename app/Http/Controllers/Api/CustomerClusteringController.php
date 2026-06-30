<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerClusterAssignment;
use App\Services\CustomerClusteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerClusteringController extends Controller
{
    public function __construct(protected CustomerClusteringService $service) {}

    /**
     * 执行聚类分析
     */
    public function runClustering(Request $request): JsonResponse
    {
        $results = $this->service->runClustering($request->user()->tenant_id);
        return ApiResponse::success($results, '聚类分析完成');
    }

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getDashboard($request->user()->tenant_id));
    }

    /**
     * 分群成员列表
     */
    public function segmentCustomers(Request $request, string $segmentKey): JsonResponse
    {
        $customerIds = CustomerClusterAssignment::where('tenant_id', $request->user()->tenant_id)
            ->where('segment_key', $segmentKey)
            ->pluck('customer_id');

        $customers = Customer::whereIn('id', $customerIds)
            ->with('contacts')
            ->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($customers);
    }

    /**
     * 客户聚类详情
     */
    public function customerCluster(Customer $customer): JsonResponse
    {
        $result = $this->service->getCustomerCluster($customer->id);
        return $result
            ? ApiResponse::success($result)
            : ApiResponse::error('NOT_FOUND', '尚未进行聚类分析', 404);
    }

    /**
     * 聚类分配历史
     */
    public function history(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            CustomerClusterAssignment::with('customer')
                ->where('tenant_id', $request->user()->tenant_id)
                ->whereNotNull('previous_segment_at')
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }
}
