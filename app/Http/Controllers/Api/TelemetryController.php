<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * SDK 心跳/Telemetry 上报控制器
 *
 * SDK 端调用：
 *  - POST /api/telemetry/heartbeat  — 心跳上报
 *  - POST /api/telemetry/events      — 事件批量上报
 *
 * 管理端调用：
 *  - GET /api/telemetry/dashboard    — 概览统计
 *  - GET /api/telemetry/heartbeats   — 心跳历史
 *  - GET /api/telemetry/versions     — 版本分布
 *  - GET /api/telemetry/events       — 事件统计
 *  - GET /api/telemetry/unhealthy    — 异常心跳
 *  - GET /api/telemetry/trend        — 版本趋势
 */
class TelemetryController extends Controller
{
    public function __construct(
        protected TelemetryService $telemetryService,
    ) {}

    // ─── SDK 公开端点 ───

    /**
     * SDK 心跳上报
     *
     * SDK 客户端定期调用此接口上报健康状态。
     * 通过 license_key + fingerprint 验证身份。
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string',
            'fingerprint' => 'required|string',
            'sdk_version' => 'nullable|string|max:30',
            'sdk_language' => 'nullable|string|max:20',
            'platform' => 'nullable|string|max:30',
            'arch' => 'nullable|string|max:10',
            'hostname' => 'nullable|string|max:100',
            'uptime' => 'nullable|integer|min:0',
            'runtime_version' => 'nullable|string|max:30',
            'health' => 'nullable|array',
            'health.cpu' => 'nullable|numeric|min:0|max:100',
            'health.memory' => 'nullable|numeric|min:0|max:100',
            'health.disk' => 'nullable|numeric|min:0|max:100',
            'features' => 'nullable|array',
            'metrics' => 'nullable|array',
            'reported_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__("app.telemetry.msg_e441b11e"), $validator->errors()->toArray());
        }

        $data = $validator->validated();

        // 查找 License
        $license = License::where('license_key', $data['license_key'])->first();

        if (!$license) {
            return ApiResponse::error('LICENSE_NOT_FOUND', 'License not found', 404);
        }

        if ($license->status !== 'active') {
            return ApiResponse::error('LICENSE_NOT_ACTIVE', 'License is not active', 403);
        }

        // 查找设备
        $device = $license->devices()
            ->where('fingerprint', $data['fingerprint'])
            ->first();

        if (!$device) {
            // 设备未注册 — 允许心跳但标记为未知设备
            // SDK 应优先调用激活进行设备注册
        }

        // 检查上报频率
        if (!$this->telemetryService->checkReportInterval($license->id, $device?->id)) {
            // 频率限制内，仍然成功返回但不处理
            return ApiResponse::success([
                'status' => 'rate_limited',
                'interval_seconds' => TelemetryService::REPORT_INTERVAL,
            ], 'Heartbeat received (rate limited)');
        }

        $heartbeat = $this->telemetryService->processHeartbeat($license, $device, $data);

        return ApiResponse::success([
            'heartbeat_id' => $heartbeat->id,
            'reported_at' => $heartbeat->reported_at->toIso8601String(),
            'interval_seconds' => TelemetryService::REPORT_INTERVAL,
        ], 'Heartbeat received');
    }

    /**
     * SDK 事件批量上报
     *
     * SDK 批量上报使用统计事件（脱敏）。
     */
    public function reportEvents(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string',
            'fingerprint' => 'required|string',
            'events' => 'required|array|min:1|max:100',
            'events.*.event_type' => 'nullable|string|max:50',
            'events.*.event_name' => 'nullable|string|max:100',
            'events.*.event_data' => 'nullable|array',
            'events.*.count' => 'nullable|integer|min:1|max:10000',
            'events.*.occurred_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.common.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();

        $license = License::where('license_key', $data['license_key'])->first();

        if (!$license) {
            return ApiResponse::error('LICENSE_NOT_FOUND', 'License not found', 404);
        }

        $count = $this->telemetryService->processEvents($license, $data['events']);

        return ApiResponse::success([
            'processed' => $count,
        ], "{$count} events processed");
    }

    // ─── 管理端端点 ───

    /**
     * Telemetry 仪表盘概览
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;

        $stats = $this->telemetryService->getDashboardStats($tenantId);

        return ApiResponse::success($stats);
    }

    /**
     * 心跳历史
     */
    public function heartbeats(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;

        $options = [
            'date_from' => $request->input('date_from', now()->subDays(7)),
            'date_to' => $request->input('date_to', now()),
            'limit' => $request->input('limit', 100),
        ];

        $licenseId = $request->input('license_id');
        $history = $this->telemetryService->getHeartbeatHistory($licenseId, $tenantId, $options);

        return ApiResponse::success($history);
    }

    /**
     * SDK 版本分布
     */
    public function versions(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;

        $distribution = $this->telemetryService->getVersionDistribution($tenantId);

        return ApiResponse::success($distribution);
    }

    /**
     * Telemetry 事件统计
     */
    public function events(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $stats = $this->telemetryService->getEventStats($tenantId, $dateFrom, $dateTo);

        return ApiResponse::success($stats);
    }

    /**
     * 异常心跳
     */
    public function unhealthy(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $limit = $request->input('limit', 50);

        $unhealthy = $this->telemetryService->getUnhealthyHeartbeats($tenantId, $limit);

        return ApiResponse::success($unhealthy);
    }

    /**
     * 版本快照趋势
     */
    public function trend(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $days = (int) $request->input('days', 30);

        $trend = $this->telemetryService->getVersionSnapshotTrend($tenantId, $days);

        return ApiResponse::success($trend);
    }
}
