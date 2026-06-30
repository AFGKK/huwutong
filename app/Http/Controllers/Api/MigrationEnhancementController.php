<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MigrationImport;
use App\Services\MigrationEnhancementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MigrationEnhancementController extends Controller
{
    public function __construct(protected MigrationEnhancementService $service) {}

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getDashboard($request->user()->tenant_id));
    }

    /**
     * 导入列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            MigrationImport::with('user:id,name')
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    /**
     * 从API源创建导入
     */
    public function createApiImport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => 'required|in:keygen,licensespring',
            'api_key' => 'required|string',
            'account_id' => 'nullable|string',
            'field_mapping' => 'nullable|array',
        ]);

        $import = $this->service->createApiImport(
            $request->user()->tenant_id,
            $request->user()->id,
            $validated['source'],
            $validated
        );

        return ApiResponse::created($import, '导入任务已创建');
    }

    /**
     * 从文件创建导入
     */
    public function createFileImport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,json,xlsx|max:10240',
            'field_mapping' => 'nullable|array',
        ]);

        $import = $this->service->createFileImport(
            $request->user()->tenant_id,
            $request->user()->id,
            $request->file('file'),
            $validated
        );

        return ApiResponse::created($import, '文件导入任务已创建');
    }

    /**
     * 执行导入
     */
    public function run(MigrationImport $migrationImport): JsonResponse
    {
        if ($migrationImport->status === 'running') {
            return ApiResponse::error('IMPORT_IN_PROGRESS', '导入任务正在运行', 409);
        }

        // 异步执行 - 实际应使用队列
        $this->service->runImport($migrationImport);

        return ApiResponse::success(
            $migrationImport->fresh()->load('user:id,name'),
            '导入完成'
        );
    }

    /**
     * 导入详情
     */
    public function show(MigrationImport $migrationImport): JsonResponse
    {
        $report = $this->service->getReport($migrationImport);
        return ApiResponse::success($report);
    }

    /**
     * 获取源信息
     */
    public function sources(): JsonResponse
    {
        return ApiResponse::success(config('migration-enhancement.sources'));
    }
}
