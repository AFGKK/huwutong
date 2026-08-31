<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CspConfig;
use App\Services\CspManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CspConfigController extends Controller
{
    public function __construct(
        protected CspManagerService $cspManager,
    ) {}

    public function index(): \Illuminate\Http\JsonResponse
    {
        $configs = CspConfig::orderBy('priority', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        return ApiResponse::success($configs);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'mode' => 'sometimes|in:enforce,report-only',
            'directives' => 'required|array',
            'route_pattern' => 'nullable|string|max:200',
            'priority' => 'sometimes|integer|min:-100|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__("app.csp_config.msg_e441b11e"), $validator->errors()->toArray());
        }

        $config = $this->cspManager->create(array_merge(
            $validator->validated(),
            ['created_by' => $request->user()?->id],
        ));

        return ApiResponse::created($config, __("app.csp_config.msg_8c2b32b3"));
    }

    public function show(CspConfig $cspConfig): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success($cspConfig);
    }

    public function update(Request $request, CspConfig $cspConfig): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
            'mode' => 'sometimes|in:enforce,report-only',
            'directives' => 'sometimes|array',
            'route_pattern' => 'nullable|string|max:200',
            'priority' => 'sometimes|integer|min:-100|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.common.validation_failed'), $validator->errors()->toArray());
        }

        $config = $this->cspManager->update($cspConfig, $validator->validated());

        return ApiResponse::success($config, __("app.csp_config.msg_43dcd34c"));
    }

    public function destroy(CspConfig $cspConfig): \Illuminate\Http\JsonResponse
    {
        $this->cspManager->delete($cspConfig);
        return ApiResponse::success(null, __("app.csp_config.msg_09567b9f"));
    }

    /**
     * 解析 CSP 策略字符串预览
     */
    public function preview(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'directives' => 'required|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.common.validation_failed'), $validator->errors()->toArray());
        }

        $directives = $request->input('directives');
        $parts = [];
        foreach ($directives as $directive => $sources) {
            $sources = (array) $sources;
            if (empty($sources)) continue;
            $parts[] = $directive . ' ' . implode(' ', $sources);
        }
        $policyString = implode('; ', $parts);

        return ApiResponse::success([
            'policy_string' => $policyString,
        ]);
    }
}
