<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockRule;
use App\Services\MockServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M1.4-59 API Mock Server 管理控制器
 */
class MockServerController extends Controller
{
    public function __construct(
        protected MockServerService $mockService,
    ) {}

    /**
     * 规则列表
     *
     * GET /api/admin/mock-server/rules
     */
    public function rules(): JsonResponse
    {
        return response()->json(['data' => $this->mockService->getRules()]);
    }

    /**
     * 创建规则
     *
     * POST /api/admin/mock-server/rules
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE',
            'path' => 'required|string|max:255',
            'status_code' => 'required|integer|between:100,599',
            'response' => 'required',
            'description' => 'nullable|string|max:500',
            'delay_ms' => 'nullable|integer|min:0|max:30000',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $rule = $this->mockService->createRule($validated);
        return response()->json(['data' => $rule, 'message' => __('app.controller_compat.mock_server_mock')], 201);
    }

    /**
     * 更新规则
     *
     * PUT /api/admin/mock-server/rules/{id}
     */
    public function update(Request $request, MockRule $mockRule): JsonResponse
    {
        $validated = $request->validate([
            'method' => 'sometimes|string|in:GET,POST,PUT,PATCH,DELETE',
            'path' => 'sometimes|string|max:255',
            'status_code' => 'sometimes|integer|between:100,599',
            'response' => 'sometimes',
            'description' => 'nullable|string|max:500',
            'delay_ms' => 'nullable|integer|min:0|max:30000',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $rule = $this->mockService->updateRule($mockRule, $validated);
        return response()->json(['data' => $rule, 'message' => __('app.controller_compat.mock_server_mock_1')]);
    }

    /**
     * 删除规则
     *
     * DELETE /api/admin/mock-server/rules/{id}
     */
    public function destroy(MockRule $mockRule): JsonResponse
    {
        $this->mockService->deleteRule($mockRule);
        return response()->json(['message' => __('app.controller_compat.mock_server_mock_2')]);
    }

    /**
     * 导入预建规则
     *
     * POST /api/admin/mock-server/import
     */
    public function import(): JsonResponse
    {
        $count = $this->mockService->importPrebuilt();
        return response()->json(['message' => "已导入 {$count} 条预建 Mock 规则", 'count' => $count]);
    }

    /**
     * 测试 Mock 端点
     *
     * POST /api/mock/{path}
     */
    public function mock(Request $request, string $path): JsonResponse
    {
        $method = $request->method();
        $result = $this->mockService->handle($method, '/' . ltrim($path, '/'));

        return response()->json($result['data'], $result['status_code']);
    }

    /**
     * 获取响应模板
     *
     * GET /api/admin/mock-server/templates
     */
    public function templates(): JsonResponse
    {
        return response()->json(['data' => config('mock-server.response_templates', [])]);
    }

    /**
     * 获取配置
     *
     * GET /api/admin/mock-server/config
     */
    public function config(): JsonResponse
    {
        return response()->json(config('mock-server'));
    }
}
