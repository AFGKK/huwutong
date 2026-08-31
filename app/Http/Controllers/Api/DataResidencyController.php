<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataMigration;
use App\Models\DataResidencyRecord;
use App\Models\Tenant;
use App\Services\DataResidencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 数据本地化存储控制器 (M3-60)
 */
class DataResidencyController extends Controller
{
    public function __construct(
        protected DataResidencyService $residencyService,
    ) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->residencyService->getDashboard(),
        ]);
    }

    /**
     * 区域列表
     */
    public function regions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->residencyService->getRegions(),
        ]);
    }

    /**
     * 为租户分配区域
     */
    public function assignTenantRegion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'region' => 'required|string',
        ]);

        $tenant = $this->residencyService->assignTenantRegion(
            $validated['tenant_id'],
            $validated['region'],
        );

        return response()->json([
            'success' => true,
            'data' => $tenant,
            'message' => "租户 {$tenant->name} 已分配区域 {$validated['region']}",
        ]);
    }

    /**
     * 创建区域绑定记录
     */
    public function createRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'region_code' => 'required|string',
            'data_classification' => 'required|string',
        ]);

        $record = $this->residencyService->createResidencyRecord(
            $validated['tenant_id'],
            $validated['region_code'],
            $validated['data_classification'],
        );

        return response()->json([
            'success' => true,
            'data' => $record,
            'message' => "数据分类 {$validated['data_classification']} 已绑定到 {$validated['region_code']}",
        ], 201);
    }

    /**
     * 区域绑定列表
     */
    public function records(): JsonResponse
    {
        $records = DataResidencyRecord::with('tenant')
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 20));

        return response()->json(['success' => true, 'data' => $records]);
    }

    /**
     * 解析存储目标 (自动路由)
     */
    public function resolveTarget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'data_classification' => 'required|string',
        ]);

        $target = $this->residencyService->resolveStorageTarget(
            $validated['tenant_id'],
            $validated['data_classification'],
        );

        return response()->json(['success' => true, 'data' => $target]);
    }

    /**
     * 启动迁移
     */
    public function startMigration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'source_region' => 'required|string',
            'target_region' => 'required|string',
            'data_classification' => 'required|string',
        ]);

        $migration = $this->residencyService->startMigration(
            $validated['tenant_id'],
            $validated['source_region'],
            $validated['target_region'],
            $validated['data_classification'],
        );

        return response()->json([
            'success' => true,
            'data' => $migration,
            'message' => __('app.controller_compat.data_residency_msg_142'),
        ], 201);
    }

    /**
     * 迁移列表
     */
    public function migrations(): JsonResponse
    {
        $migrations = DataMigration::orderBy('created_at', 'desc')
            ->paginate(request('per_page', 20));

        return response()->json(['success' => true, 'data' => $migrations]);
    }

    /**
     * 合规审计
     */
    public function complianceAudit(): JsonResponse
    {
        $audit = $this->residencyService->getComplianceAudit();

        return response()->json(['success' => true, 'data' => $audit]);
    }

    /**
     * 数据分类列表
     */
    public function classifications(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->residencyService->getDataClassifications(),
        ]);
    }

    /**
     * 租户列表 (带区域信息)
     */
    public function tenants(): JsonResponse
    {
        $tenants = Tenant::select('id', 'name', 'data_region', 'created_at')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $tenants]);
    }
}
