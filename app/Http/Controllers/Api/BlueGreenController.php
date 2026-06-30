<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlueGreenDeployment;
use App\Services\BlueGreenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 蓝绿部署控制器 (M3-63)
 */
class BlueGreenController extends Controller
{
    public function __construct(
        protected BlueGreenService $blueGreen,
    ) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->blueGreen->getDashboard();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 部署历史
     */
    public function history(): JsonResponse
    {
        $data = $this->blueGreen->getHistory();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 开始部署
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'release_id' => 'required|integer|exists:deploy_releases,id',
            'notes' => 'nullable|string',
        ]);

        $deployment = $this->blueGreen->startDeployment(
            $validated['release_id'],
            $validated['notes'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => $deployment,
            'message' => "蓝绿部署已启动，预热 {$deployment->standby_environment} 环境",
        ], 201);
    }

    /**
     * 健康检查
     */
    public function healthCheck(int $id): JsonResponse
    {
        $result = $this->blueGreen->runHealthChecks($id);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $result['all_healthy'] ? '✅ 所有健康检查通过' : '❌ 部分健康检查失败',
        ]);
    }

    /**
     * 验证环境
     */
    public function verify(int $id): JsonResponse
    {
        $result = $this->blueGreen->runVerification($id);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $result['passed'] ? '✅ 验证通过，准备切换' : '❌ 验证失败',
        ]);
    }

    /**
     * 切换流量
     */
    public function switch(int $id): JsonResponse
    {
        $deployment = $this->blueGreen->switchTraffic($id);

        return response()->json([
            'success' => true,
            'data' => $deployment,
            'message' => "✅ 流量已切换到 {$deployment->active_environment} 环境",
        ]);
    }

    /**
     * 回滚
     */
    public function rollback(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $deployment = $this->blueGreen->rollback(
            $id,
            $validated['reason'] ?? '手动回滚',
        );

        return response()->json([
            'success' => true,
            'data' => $deployment,
            'message' => "↩️ 已回滚到 {$deployment->active_environment} 环境",
        ]);
    }

    /**
     * 部署详情
     */
    public function show(int $id): JsonResponse
    {
        $deployment = BlueGreenDeployment::findOrFail($id);

        return response()->json(['success' => true, 'data' => $deployment]);
    }
}
