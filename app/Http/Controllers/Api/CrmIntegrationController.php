<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CrmConnection;
use App\Models\CrmSyncLog;
use App\Services\CrmIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmIntegrationController extends Controller
{
    public function __construct(protected CrmIntegrationService $service) {}

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getDashboard($request->user()->tenant_id));
    }

    /**
     * 连接CRM
     */
    public function connect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|in:hubspot,salesforce',
            'credentials' => 'required|array',
        ]);

        try {
            $connection = $this->service->connect(
                $request->user()->tenant_id,
                $validated['provider'],
                $validated['credentials']
            );
            return ApiResponse::success($connection, __("app.crm_integration.msg_5324a8e5"));
        } catch (\Exception $e) {
            return ApiResponse::error('CONNECT_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 断开连接
     */
    public function disconnect(CrmConnection $crmConnection): JsonResponse
    {
        $this->service->disconnect($crmConnection);
        return ApiResponse::success(null, __("app.crm_integration.msg_264d03b3"));
    }

    /**
     * 推送到CRM
     */
    public function push(Request $request, CrmConnection $crmConnection): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => 'required|in:customer,license',
            'ids' => 'nullable|array',
        ]);

        $log = $this->service->pushToCrm($crmConnection, $validated['entity_type'], $validated['ids'] ?? []);
        return ApiResponse::success($log, __("app.crm_integration.msg_2852a3a6"));
    }

    /**
     * 从CRM拉取
     */
    public function pull(Request $request, CrmConnection $crmConnection): JsonResponse
    {
        $request->validate(['entity_type' => 'required|in:customer,license']);
        $log = $this->service->pullFromCrm($crmConnection, $request->entity_type);
        return ApiResponse::success($log, __("app.crm_integration.msg_ee613ae4"));
    }

    /**
     * 同步日志
     */
    public function logs(CrmConnection $crmConnection): JsonResponse
    {
        return ApiResponse::paginated(
            $crmConnection->syncLogs()->latest()->paginate(20)
        );
    }

    /**
     * 连接详情
     */
    public function show(CrmConnection $crmConnection): JsonResponse
    {
        $crmConnection->load(['syncLogs' => fn($q) => $q->latest()->limit(10)]);
        return ApiResponse::success($crmConnection);
    }
}
