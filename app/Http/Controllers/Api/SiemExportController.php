<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SiemConnection;
use App\Services\SiemExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SIEM 审计日志导出控制器 (M2-52)
 */
class SiemExportController extends Controller
{
    public function __construct(
        protected SiemExportService $siemExport,
    ) {
    }

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success(
            $this->siemExport->getDashboard($tenantId)
        );
    }

    /**
     * 获取连接列表
     */
    public function index(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success([
            'connections' => $this->siemExport->getConnections($tenantId),
        ]);
    }

    /**
     * 创建连接
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'format' => 'required|string|in:cef,elk_json,sls',
            'endpoint_url' => 'nullable|string|url|max:500',
            'auth_type' => 'nullable|string|in:none,bearer_token,basic,api_key',
            'auth_credentials' => 'nullable|string',
            'field_mappings' => 'nullable|array',
            'filters' => 'nullable|array',
            'is_active' => 'boolean',
            'auto_push' => 'boolean',
            'push_frequency' => 'nullable|string|in:realtime,hourly,daily',
            'max_batch_size' => 'nullable|integer|min:1|max:10000',
            'notes' => 'nullable|string|max:500',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $connection = $this->siemExport->saveConnection($tenantId, $data);

        return ApiResponse::success(['connection' => $connection], __("app.siem_export.msg_a770cb14"));
    }

    /**
     * 更新连接
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $connection = SiemConnection::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $data = $request->validate([
            'name' => 'nullable|string|max:100',
            'format' => 'nullable|string|in:cef,elk_json,sls',
            'endpoint_url' => 'nullable|string|url|max:500',
            'auth_type' => 'nullable|string|in:none,bearer_token,basic,api_key',
            'auth_credentials' => 'nullable|string',
            'field_mappings' => 'nullable|array',
            'filters' => 'nullable|array',
            'is_active' => 'boolean',
            'auto_push' => 'boolean',
            'push_frequency' => 'nullable|string|in:realtime,hourly,daily',
            'max_batch_size' => 'nullable|integer|min:1|max:10000',
            'notes' => 'nullable|string|max:500',
        ]);

        $updated = $this->siemExport->saveConnection(auth()->user()->tenant_id, $data, $id);
        return ApiResponse::success(['connection' => $updated], __("app.siem_export.msg_babd1758"));
    }

    /**
     * 删除连接
     */
    public function destroy(int $id): JsonResponse
    {
        SiemConnection::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $this->siemExport->deleteConnection($id);
        return ApiResponse::success(null, __("app.siem_export.msg_666bcbcb"));
    }

    /**
     * 测试连接
     */
    public function test(int $id): JsonResponse
    {
        SiemConnection::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $result = $this->siemExport->testConnection($id);
        return $result['success']
            ? ApiResponse::success($result, __("app.siem_export.msg_f20bc5e4"))
            : ApiResponse::error($result['message'] ?? __("app.siem_export.msg_f91780a3"), 422, $result);
    }

    /**
     * 推送日志
     */
    public function push(Request $request, int $id): JsonResponse
    {
        SiemConnection::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $filters = $request->validate([
            'event_type' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'severity' => 'nullable|string',
        ]);

        $result = $this->siemExport->pushLogs($id, $filters);

        return $result['success'] ?? true
            ? ApiResponse::success($result, __("app.siem_export.msg_b1fd88b6"))
            : ApiResponse::error($result['message'] ?? __("app.siem_export.msg_3b6ba072"), 422, $result);
    }

    /**
     * 获取推送日志
     */
    public function logs(int $id): JsonResponse
    {
        SiemConnection::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return ApiResponse::success([
            'logs' => $this->siemExport->getPushLogs($id),
        ]);
    }

    /**
     * 获取连接统计
     */
    public function stats(int $id): JsonResponse
    {
        SiemConnection::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return ApiResponse::success(
            $this->siemExport->getConnectionStats($id)
        );
    }

    /**
     * 获取格式预览
     */
    public function formatPreview(string $format): JsonResponse
    {
        return ApiResponse::success(
            $this->siemExport->getFormatPreview($format)
        );
    }

    /**
     * 获取支持的格式列表
     */
    public function formats(): JsonResponse
    {
        return ApiResponse::success([
            'formats' => config('siem-export.formats', []),
            'default_format' => config('siem-export.default_format', 'elk_json'),
        ]);
    }
}
