<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SdkIntegrityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-17 SDK完整性自检 + 远程自毁 API
 */
class SdkIntegrityController extends Controller
{
    public function __construct(
        private readonly SdkIntegrityService $sdkIntegrity,
    ) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->sdkIntegrity->getDashboard(),
        ]);
    }

    /**
     * SDK提交完整性检查结果
     */
    public function submitCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sdk_instance_id' => 'required|string|max:64',
            'language' => 'required|string|max:20',
            'sdk_version' => 'required|string|max:20',
            'machine_id' => 'nullable|string|max:64',
            'passed' => 'required|boolean',
            'file_checksums' => 'nullable|array',
            'file_checksums.*' => 'string',
            'failed_files' => 'nullable|array',
            'failed_files.*' => 'string',
            'error_message' => 'nullable|string|max:500',
        ]);

        $check = $this->sdkIntegrity->submitCheck($validated);

        return response()->json([
            'code' => 0,
            'message' => $check->passed ? '校验通过' : '校验失败已记录',
            'data' => [
                'id' => $check->id,
                'passed' => $check->passed,
                'checked_at' => $check->checked_at,
            ],
        ]);
    }

    /**
     * SDK完整性检查记录列表
     */
    public function checks(Request $request): JsonResponse
    {
        $filters = $request->only(['sdk_instance_id', 'language', 'passed', 'date_from', 'date_to']);

        return response()->json([
            'code' => 0,
            'data' => $this->sdkIntegrity->getChecks($filters, $request->input('per_page', 20)),
        ]);
    }

    /**
     * 下发远程销毁命令
     */
    public function issueDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sdk_instance_id' => 'nullable|string|max:64',
            'language' => 'nullable|string|max:20',
            'version_constraint' => 'nullable|string|max:50',
            'reason' => 'required|string|max:500',
            'destroy_mode' => 'nullable|string|in:soft,hard',
        ]);

        $command = $this->sdkIntegrity->issueDestroyCommand(
            sdkInstanceId: $validated['sdk_instance_id'] ?? null,
            language: $validated['language'] ?? null,
            versionConstraint: $validated['version_constraint'] ?? null,
            reason: $validated['reason'],
            destroyMode: $validated['destroy_mode'] ?? 'soft',
            createdBy: $request->user()?->id,
        );

        return response()->json([
            'code' => 0,
            'message' => __('app.controller_compat.sdk_integrity_msg_98'),
            'data' => $command,
        ]);
    }

    /**
     * SDK轮询销毁命令（公开，SDK使用）
     */
    public function pollDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sdk_instance_id' => 'required|string|max:64',
        ]);

        $result = $this->sdkIntegrity->pollDestroyCommand($validated['sdk_instance_id']);

        return response()->json([
            'code' => 0,
            'data' => $result,
        ]);
    }

    /**
     * SDK确认销毁（公开，SDK使用）
     */
    public function confirmDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command_id' => 'required|string|max:64',
            'sdk_instance_id' => 'required|string|max:64',
        ]);

        $result = $this->sdkIntegrity->confirmDestroy($validated['command_id'], $validated['sdk_instance_id']);

        return response()->json([
            'code' => 0,
            'message' => $result ? '销毁确认成功' : '命令未找到',
            'data' => ['confirmed' => $result],
        ]);
    }

    /**
     * SDK心跳（公开，SDK使用）
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sdk_instance_id' => 'required|string|max:64',
            'language' => 'nullable|string|max:20',
            'sdk_version' => 'nullable|string|max:20',
            'machine_id' => 'nullable|string|max:64',
        ]);

        return response()->json([
            'code' => 0,
            'data' => $this->sdkIntegrity->heartbeat($validated),
        ]);
    }

    /**
     * SDK获取完整性配置（公开，SDK启动时调用）
     */
    public function sdkConfig(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->sdkIntegrity->getSdkConfig(),
        ]);
    }

    /**
     * 获取受保护文件清单
     */
    public function protectedFiles(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->sdkIntegrity->getProtectedFiles($request->input('language')),
        ]);
    }

    /**
     * 销毁命令列表
     */
    public function commands(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'trigger_type', 'language', 'sdk_instance_id']);

        return response()->json([
            'code' => 0,
            'data' => $this->sdkIntegrity->getCommands($filters, $request->input('per_page', 20)),
        ]);
    }

    /**
     * 取消销毁命令
     */
    public function cancelCommand(int $id): JsonResponse
    {
        $command = $this->sdkIntegrity->cancelCommand($id);

        if (!$command) {
            return response()->json(['code' => 1, 'message' => __('app.controller_compat.sdk_integrity_msg_200')], 422);
        }

        return response()->json([
            'code' => 0,
            'message' => __('app.controller_compat.sdk_integrity_msg_205'),
            'data' => $command,
        ]);
    }

    /**
     * 批量销毁
     */
    public function batchDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'nullable|string|max:20',
            'sdk_version' => 'nullable|string|max:20',
            'failed_only' => 'nullable|boolean',
            'date_before' => 'nullable|date',
            'reason' => 'required|string|max:500',
            'destroy_mode' => 'nullable|string|in:soft,hard',
        ]);

        $results = $this->sdkIntegrity->batchDestroy(
            criteria: $validated,
            reason: $validated['reason'],
            mode: $validated['destroy_mode'] ?? 'soft',
            userId: $request->user()?->id,
        );

        $count = count($results);
        return response()->json([
            'code' => 0,
            'message' => "已下发 {$count} 个批量销毁命令",
            'data' => ['command_ids' => $results, 'count' => $count],
        ]);
    }

    /**
     * 处理过期命令
     */
    public function processExpired(): JsonResponse
    {
        $count = $this->sdkIntegrity->processExpiredCommands();

        return response()->json([
            'code' => 0,
            'message' => "已处理 {$count} 个过期命令",
            'data' => ['processed' => $count],
        ]);
    }
}
