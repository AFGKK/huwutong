<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MigrationAssistantJob;
use App\Services\MigrationAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MigrationAssistantController extends Controller
{
    public function __construct(protected MigrationAssistantService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getDashboard($request->user()->tenant_id));
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            MigrationAssistantJob::with('user:id,name')
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => 'required|in:cryptlex,localazy',
            'api_key' => 'required|string',
            'field_mapping' => 'nullable|array',
        ]);

        $job = $this->service->createJob(
            $request->user()->tenant_id,
            $request->user()->id,
            $validated['source'],
            $validated
        );

        return ApiResponse::created($job, '迁移任务已创建');
    }

    public function run(MigrationAssistantJob $migrationAssistantJob): JsonResponse
    {
        if ($migrationAssistantJob->status === 'importing') {
            return ApiResponse::error('IN_PROGRESS', '任务正在执行中', 409);
        }

        // 异步 - 实际用队列
        $this->service->runImport($migrationAssistantJob);

        return ApiResponse::success(
            $migrationAssistantJob->fresh()->load('user:id,name'),
            '迁移完成'
        );
    }

    public function show(MigrationAssistantJob $migrationAssistantJob): JsonResponse
    {
        return ApiResponse::success($this->service->getReport($migrationAssistantJob));
    }

    public function sources(): JsonResponse
    {
        return ApiResponse::success(collect(config('migration-assistant.sources'))
            ->map(fn($s, $k) => ['key' => $k, 'name' => $s['name'], 'enabled' => $s['enabled']])
            ->values());
    }
}
