<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\LifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LifecycleController extends Controller
{
    public function __construct(
        protected LifecycleService $service
    ) {}

    /**
     * 生命周期仪表盘
     */
    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->service->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 阶段迁移历史
     */
    public function transitions(Request $request)
    {
        return ApiResponse::success(
            $this->service->getTransitionHistory(
                $request->user()->tenant_id,
                $request->only(['stage', 'customer_id', 'triggered_by', 'per_page'])
            )
        );
    }

    /**
     * 手动迁移客户阶段
     */
    public function transition(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer',
            'stage' => 'required|string|in:' . implode(',', array_keys(\App\Models\CustomerLifecycleStage::STAGES)),
            'reason' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $customer = Customer::where('id', $request->customer_id)
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        return ApiResponse::success(
            $this->service->transitionCustomer($customer, $request->stage, $request->reason, 'manual')
        );
    }

    /**
     * 批量自动评估
     */
    public function autoEvaluate(Request $request)
    {
        return ApiResponse::success(
            $this->service->autoEvaluate($request->user()->tenant_id)
        );
    }

    /**
     * 客户生命周期评分
     */
    public function customerScore(Request $request, Customer $customer)
    {
        return ApiResponse::success(
            $this->service->getLifecycleScore($customer)
        );
    }

    /**
     * 客户阶段建议
     */
    public function suggest(Request $request, Customer $customer)
    {
        return ApiResponse::success([
            'suggested_stage' => $this->service->suggestStage($customer),
            'current_stage' => $customer->lifecycle_stage ?? 'prospect',
        ]);
    }
}
