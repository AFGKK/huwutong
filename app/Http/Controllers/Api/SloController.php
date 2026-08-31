<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SloDefinition;
use App\Services\SloBudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SloController extends Controller
{
    public function __construct(
        protected SloBudgetService $sloBudgetService,
    ) {}

    // ─── 仪表盘 ───

    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->sloBudgetService->getDashboard($request->user()->tenant_id)
        );
    }

    // ─── SLO定义 CRUD ───

    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->sloBudgetService->listDefinitions(
                $request->user()->tenant_id,
                $request->only(['service_name', 'sli_type', 'is_active', 'search', 'per_page'])
            )
        );
    }

    public function show(int $id)
    {
        return ApiResponse::success($this->sloBudgetService->getDefinition($id));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'service_name' => 'required|string|max:200',
            'sli_type' => 'required|string|in:latency,availability,throughput,error_rate',
            'target' => 'required|numeric|between:50,99.999',
            'window_days' => 'nullable|numeric|min:1|max:365',
            'burn_rate_alerts' => 'nullable|array',
            'burn_rate_alerts.*.window_hours' => 'required|numeric|min:1',
            'burn_rate_alerts.*.threshold' => 'required|numeric|min:0.1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->sloBudgetService->createDefinition($data), 201);
    }

    public function update(Request $request, SloDefinition $sloDefinition)
    {
        $data = $request->only([
            'name', 'description', 'service_name', 'sli_type',
            'target', 'window_days', 'burn_rate_alerts', 'is_active',
        ]);

        return ApiResponse::success($this->sloBudgetService->updateDefinition($sloDefinition, $data));
    }

    public function destroy(SloDefinition $sloDefinition)
    {
        $this->sloBudgetService->deleteDefinition($sloDefinition);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 错误预算计算 ───

    public function calculate(SloDefinition $sloDefinition)
    {
        return ApiResponse::success(
            $this->sloBudgetService->calculateBudget($sloDefinition)
        );
    }

    public function calculateAll()
    {
        $count = $this->sloBudgetService->calculateAllBudgets();
        return ApiResponse::success(['calculated' => $count], __("app.slo.msg_4b9df144"));
    }

    // ─── 元数据 ───

    public function sliTypes()
    {
        return ApiResponse::success([
            'latency' => __('app.slo.latency'),
            'availability' => __('app.slo.availability'),
            'throughput' => __('app.slo.throughput'),
            'error_rate' => __('app.slo.error_rate'),
        ]);
    }
}
