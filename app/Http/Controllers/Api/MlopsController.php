<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MlModel;
use App\Models\MlModelVersion;
use App\Models\MlTrainingJob;
use App\Services\MlopsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI MLOps 平台控制器 (M3-40)
 *
 * 模型版本管理 + 漂移监控 + 自动重训练
 */
class MlopsController extends Controller
{
    public function __construct(
        protected MlopsService $mlops,
    ) {}

    /**
     * 仪表盘
     *
     * GET /api/admin/mlops/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success($this->mlops->getDashboard($tenantId));
    }

    /**
     * 模型列表
     *
     * GET /api/admin/mlops/models
     */
    public function models(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['framework', 'task_type', 'status', 'search']);
        return ApiResponse::success($this->mlops->listModels($tenantId, $filters));
    }

    /**
     * 创建模型
     *
     * POST /api/admin/mlops/models
     */
    public function storeModel(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'model_key' => 'nullable|string|max:100|unique:ml_models,model_key',
            'framework' => 'required|in:tensorflow,pytorch,onnx,sklearn,xgboost',
            'task_type' => 'required|string|max:50',
            'description' => 'nullable|string|max:2000',
            'config' => 'nullable|array',
            'features' => 'nullable|array',
            'metrics_definitions' => 'nullable|array',
        ]);

        $model = $this->mlops->createModel($tenantId, $validated);
        return ApiResponse::created($model, '模型创建成功');
    }

    /**
     * 模型详情
     *
     * GET /api/admin/mlops/models/{model}
     */
    public function showModel(MlModel $model): JsonResponse
    {
        $this->authorize('view', $model);
        return ApiResponse::success($this->mlops->getModel($model));
    }

    /**
     * 更新模型
     *
     * PUT /api/admin/mlops/models/{model}
     */
    public function updateModel(Request $request, MlModel $model): JsonResponse
    {
        $this->authorize('update', $model);

        $validated = $request->validate([
            'name' => 'string|max:200',
            'framework' => 'in:tensorflow,pytorch,onnx,sklearn,xgboost',
            'task_type' => 'string|max:50',
            'description' => 'nullable|string|max:2000',
            'status' => 'in:active,archived,deprecated',
            'config' => 'nullable|array',
            'features' => 'nullable|array',
            'metrics_definitions' => 'nullable|array',
        ]);

        $model = $this->mlops->updateModel($model, $validated);
        return ApiResponse::success($model, '模型已更新');
    }

    /**
     * 删除模型
     *
     * DELETE /api/admin/mlops/models/{model}
     */
    public function destroyModel(MlModel $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return ApiResponse::success(null, '模型已删除');
    }

    // ═══════ 版本管理 ═══════

    /**
     * 版本列表
     *
     * GET /api/admin/mlops/models/{model}/versions
     */
    public function versions(Request $request, MlModel $model): JsonResponse
    {
        $filters = $request->only(['status']);
        return ApiResponse::success($this->mlops->listVersions($model->id, $filters));
    }

    /**
     * 创建版本
     *
     * POST /api/admin/mlops/models/{model}/versions
     */
    public function storeVersion(Request $request, MlModel $model): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:' . (config('mlops.models.max_file_size_mb', 500) * 1024),
            'version' => 'nullable|string|max:30',
            'metrics' => 'nullable|array',
            'hyperparameters' => 'nullable|array',
        ]);

        $version = $this->mlops->createVersion($model->id, $validated);
        return ApiResponse::created($version, '版本创建成功');
    }

    /**
     * 部署版本
     *
     * POST /api/admin/mlops/models/{model}/versions/{version}/deploy
     */
    public function deployVersion(MlModel $model, MlModelVersion $version): JsonResponse
    {
        $version = $this->mlops->deployVersion($version, auth()->id());
        return ApiResponse::success($version, '版本已部署到生产');
    }

    /**
     * 回滚版本
     *
     * POST /api/admin/mlops/models/{model}/rollback/{version}
     */
    public function rollbackVersion(MlModel $model, MlModelVersion $version): JsonResponse
    {
        $version = $this->mlops->rollbackVersion($model, $version, auth()->id());
        return ApiResponse::success($version, '版本已回滚');
    }

    // ═══════ 训练任务 ═══════

    /**
     * 训练任务列表
     *
     * GET /api/admin/mlops/training-jobs
     */
    public function trainingJobs(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['status', 'ml_model_id']);
        return ApiResponse::success($this->mlops->listTrainingJobs($tenantId, $filters));
    }

    /**
     * 提交训练任务
     *
     * POST /api/admin/mlops/models/{model}/train
     */
    public function submitTraining(Request $request, MlModel $model): JsonResponse
    {
        $validated = $request->validate([
            'epochs' => 'nullable|integer|min:1|max:500',
            'batch_size' => 'nullable|integer|min:1|max:256',
            'early_stopping_patience' => 'nullable|integer|min:0|max:100',
        ]);

        $job = $this->mlops->submitTrainingJob($model->id, $validated, auth()->id());
        return ApiResponse::created($job, '训练任务已提交');
    }

    // ═══════ 漂移监控 ═══════

    /**
     * 漂移事件列表
     *
     * GET /api/admin/mlops/drift-events
     */
    public function driftEvents(Request $request): JsonResponse
    {
        $filters = $request->only(['ml_model_id', 'severity', 'metric', 'from', 'to']);
        return ApiResponse::success($this->mlops->listDriftEvents($filters));
    }

    /**
     * 漂移摘要
     *
     * GET /api/admin/mlops/drift-summary
     */
    public function driftSummary(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $mlModelId = $request->integer('ml_model_id') ?: null;
        return ApiResponse::success($this->mlops->getDriftSummary($tenantId, $mlModelId));
    }

    /**
     * 手动检测漂移
     *
     * POST /api/admin/mlops/models/{model}/detect-drift
     */
    public function detectDrift(Request $request, MlModel $model): JsonResponse
    {
        $validated = $request->validate([
            'metrics' => 'required|array',
            'metrics.accuracy' => 'nullable|numeric',
            'metrics.precision' => 'nullable|numeric',
            'metrics.recall' => 'nullable|numeric',
            'metrics.f1' => 'nullable|numeric',
        ]);

        $productionVersion = $model->productionVersion;
        if (!$productionVersion) {
            return ApiResponse::error('没有生产版本可用于漂移检测');
        }

        $event = $this->mlops->detectDrift($productionVersion->id, $validated['metrics']);
        if (!$event) {
            return ApiResponse::success(null, '未检测到显著漂移');
        }

        return ApiResponse::success($event, '漂移事件已记录');
    }
}
