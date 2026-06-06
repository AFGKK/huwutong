<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CorsConfig;
use App\Services\CorsManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CorsConfigController extends Controller
{
    public function __construct(
        protected CorsManagerService $corsManager,
    ) {}

    /**
     * 获取所有 CORS 配置
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $configs = CorsConfig::orderBy('priority', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        return ApiResponse::success($configs);
    }

    /**
     * 创建 CORS 配置
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'allowed_origins' => 'required|array',
            'allowed_origins.*' => 'string',
            'allowed_methods' => 'sometimes|array',
            'allowed_methods.*' => 'string',
            'allowed_headers' => 'sometimes|array',
            'allowed_headers.*' => 'string',
            'exposed_headers' => 'sometimes|array',
            'exposed_headers.*' => 'string',
            'allow_credentials' => 'sometimes|boolean',
            'max_age' => 'sometimes|integer|min:0|max:86400',
            'route_pattern' => 'nullable|string|max:200',
            'priority' => 'sometimes|integer|min:-100|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $config = $this->corsManager->create(array_merge(
            $validator->validated(),
            ['created_by' => $request->user()?->id],
        ));

        return ApiResponse::created($config, 'CORS 配置已创建');
    }

    /**
     * 获取单个 CORS 配置
     */
    public function show(CorsConfig $corsConfig): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success($corsConfig);
    }

    /**
     * 更新 CORS 配置
     */
    public function update(Request $request, CorsConfig $corsConfig): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
            'allowed_origins' => 'sometimes|array',
            'allowed_origins.*' => 'string',
            'allowed_methods' => 'sometimes|array',
            'allowed_methods.*' => 'string',
            'allowed_headers' => 'sometimes|array',
            'allowed_headers.*' => 'string',
            'exposed_headers' => 'sometimes|array',
            'exposed_headers.*' => 'string',
            'allow_credentials' => 'sometimes|boolean',
            'max_age' => 'sometimes|integer|min:0|max:86400',
            'route_pattern' => 'nullable|string|max:200',
            'priority' => 'sometimes|integer|min:-100|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $config = $this->corsManager->update($corsConfig, $validator->validated());

        return ApiResponse::success($config, 'CORS 配置已更新');
    }

    /**
     * 删除 CORS 配置
     */
    public function destroy(CorsConfig $corsConfig): \Illuminate\Http\JsonResponse
    {
        $this->corsManager->delete($corsConfig);

        return ApiResponse::success(null, 'CORS 配置已删除');
    }

    /**
     * 测试 CORS 配置匹配
     */
    public function test(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'origin' => 'required|string',
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        // 模拟一个请求来测试匹配
        $mockRequest = Request::create($request->input('path'), 'OPTIONS');
        $mockRequest->headers->set('Origin', $request->input('origin'));

        $config = $this->corsManager->resolveConfig($mockRequest);

        if ($config) {
            $headers = $this->corsManager->buildHeaders($mockRequest);
            return ApiResponse::success([
                'matched' => true,
                'config' => $config,
                'headers' => $headers,
            ], '匹配到 CORS 配置');
        }

        return ApiResponse::success([
            'matched' => false,
            'config' => null,
            'headers' => null,
        ], '未匹配到 CORS 配置');
    }
}
