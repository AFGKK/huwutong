<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\RoiCalculatorService;
use Illuminate\Http\Request;

class RoiCalculatorController extends Controller
{
    public function __construct(
        protected RoiCalculatorService $roiCalculatorService,
    ) {}

    /**
     * 计算ROI
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'currency' => 'nullable|in:CNY,USD',
            'developer_salary' => 'nullable|numeric|min:0',
            'developer_count' => 'nullable|integer|min:1|max:20',
            'devops_cost' => 'nullable|numeric|min:0',
            'infrastructure_cost' => 'nullable|numeric|min:0',
            'maintenance_yearly' => 'nullable|numeric|min:5|max:50',
            'compliance_cost' => 'nullable|numeric|min:0',
            'opportunity_cost' => 'nullable|numeric|min:0',
            'development_months' => 'nullable|integer|min:1|max:36',
            'license_fee' => 'nullable|numeric|min:0',
            'seat_count' => 'nullable|integer|min:1|max:10000',
            'support_fee' => 'nullable|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
        ]);

        $currency = $request->input('currency', 'CNY');
        $result = $this->roiCalculatorService->calculate($validated, $currency);

        return ApiResponse::success($result);
    }

    /**
     * 获取默认参数
     */
    public function defaults(Request $request)
    {
        $currency = $request->input('currency', 'CNY');
        return ApiResponse::success([
            'defaults' => $this->roiCalculatorService->getDefaults($currency),
            'param_definitions' => $this->roiCalculatorService->getParamDefinitions(),
        ]);
    }
}
