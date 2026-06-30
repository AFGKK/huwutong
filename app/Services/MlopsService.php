<?php

namespace App\Services;

use App\Models\MlModel;
use App\Models\MlModelVersion;
use App\Models\MlTrainingJob;
use App\Models\MlDriftEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AI MLOps 平台服务 (M3-40)
 *
 * 模型版本管理 + 漂移监控 + 自动重训练
 */
class MlopsService
{
    // ═══════ 模型管理 ═══════

    /**
     * 获取模型列表
     */
    public function listModels(int $tenantId, array $filters = []): array
    {
        $query = MlModel::where('tenant_id', $tenantId);

        if (!empty($filters['framework'])) {
            $query->where('framework', $filters['framework']);
        }
        if (!empty($filters['task_type'])) {
            $query->where('task_type', $filters['task_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('model_key', 'like', "%{$filters['search']}%");
            });
        }

        $perPage = $filters['per_page'] ?? 20;
        $results = $query->withCount('versions')->orderByDesc('id')->paginate($perPage)->withQueryString();

        return $results->toArray();
    }

    /**
     * 创建模型
     */
    public function createModel(int $tenantId, array $data): MlModel
    {
        $data['tenant_id'] = $tenantId;
        $data['model_key'] = $data['model_key'] ?? Str::slug($data['name']) . '-' . Str::random(6);

        return MlModel::create($data);
    }

    /**
     * 更新模型
     */
    public function updateModel(MlModel $model, array $data): MlModel
    {
        $model->update($data);
        return $model->fresh();
    }

    /**
     * 获取模型详情
     */
    public function getModel(MlModel $model): array
    {
        $model->load(['versions' => function ($q) {
            $q->orderByDesc('id');
        }, 'productionVersion']);

        return [
            'model' => $model,
            'version_count' => $model->versions->count(),
            'latest_version' => $model->versions->first(),
            'production_version' => $model->productionVersion,
            'recent_training_jobs' => MlTrainingJob::where('ml_model_id', $model->id)
                ->orderByDesc('id')->take(5)->get(),
            'recent_drift_events' => MlDriftEvent::whereIn('ml_model_version_id', $model->versions->pluck('id'))
                ->orderByDesc('detected_at')->take(10)->get(),
        ];
    }

    // ═══════ 版本管理 ═══════

    /**
     * 获取模型版本列表
     */
    public function listVersions(int $mlModelId, array $filters = []): array
    {
        $query = MlModelVersion::where('ml_model_id', $mlModelId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 20;
        $results = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return $results->toArray();
    }

    /**
     * 创建新版本
     */
    public function createVersion(int $mlModelId, array $data): MlModelVersion
    {
        $model = MlModel::findOrFail($mlModelId);

        // 自动生成版本号
        $latestVersion = MlModelVersion::where('ml_model_id', $mlModelId)
            ->orderByDesc('id')->first();

        if ($latestVersion) {
            $parts = explode('.', $latestVersion->version);
            $parts[2] = (int)$parts[2] + 1;
            $version = implode('.', $parts);
        } else {
            $version = 'v1.0.0';
        }

        $data['ml_model_id'] = $mlModelId;
        $data['version'] = $data['version'] ?? $version;

        if (isset($data['file']) && $data['file'] instanceof \Illuminate\Http\UploadedFile) {
            $file = $data['file'];
            $path = $file->store("ml-models/{$mlModelId}", config('mlops.models.storage_disk'));
            $data['file_path'] = $path;
            $data['file_hash'] = hash_file('sha256', $file->getRealPath());
            $data['file_size'] = $file->getSize();
            unset($data['file']);
        }

        return MlModelVersion::create($data);
    }

    /**
     * 部署版本到生产
     */
    public function deployVersion(MlModelVersion $version, int $userId): MlModelVersion
    {
        DB::transaction(function () use ($version, $userId) {
            // 取消同模型其他版本的production状态
            MlModelVersion::where('ml_model_id', $version->ml_model_id)
                ->where('status', 'production')
                ->update(['status' => 'archived']);

            $version->update([
                'status' => 'production',
                'deployed_at' => now(),
                'deployed_by' => $userId,
            ]);
        });

        return $version->fresh();
    }

    /**
     * 回滚版本
     */
    public function rollbackVersion(MlModel $model, MlModelVersion $targetVersion, int $userId): MlModelVersion
    {
        return $this->deployVersion($targetVersion, $userId);
    }

    // ═══════ 训练任务 ═══════

    /**
     * 获取训练任务列表
     */
    public function listTrainingJobs(int $tenantId, array $filters = []): array
    {
        $query = MlTrainingJob::whereHas('model', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->with('model');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['ml_model_id'])) {
            $query->where('ml_model_id', $filters['ml_model_id']);
        }

        $perPage = $filters['per_page'] ?? 20;
        $results = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return $results->toArray();
    }

    /**
     * 提交训练任务
     */
    public function submitTrainingJob(int $mlModelId, array $config, ?int $userId = null): MlTrainingJob
    {
        $model = MlModel::findOrFail($mlModelId);

        // 检查并发限制
        $running = MlTrainingJob::where('ml_model_id', $mlModelId)
            ->whereIn('status', ['pending', 'running'])
            ->count();

        $maxConcurrent = config('mlops.training.max_concurrent_jobs', 3);
        if ($running >= $maxConcurrent) {
            throw new \RuntimeException("已达到最大并发训练任务数({$maxConcurrent})");
        }

        $job = MlTrainingJob::create([
            'ml_model_id' => $mlModelId,
            'job_id' => 'train-' . Str::random(16),
            'status' => 'pending',
            'config' => array_merge([
                'epochs' => config('mlops.training.default_epochs', 100),
                'batch_size' => config('mlops.training.default_batch_size', 32),
                'early_stopping_patience' => config('mlops.training.early_stopping_patience', 10),
            ], $config),
            'triggered_by' => $userId,
        ]);

        // 实际生产环境会分发到队列
        Log::info('ML训练任务已提交', [
            'job_id' => $job->job_id,
            'model_id' => $mlModelId,
            'model' => $model->name,
        ]);

        // 模拟异步训练调度
        dispatch(function () use ($job) {
            $this->executeTrainingJob($job);
        })->afterResponse();

        return $job;
    }

    /**
     * 执行训练任务（模拟）
     */
    protected function executeTrainingJob(MlTrainingJob $job): void
    {
        try {
            $job->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            // 模拟训练过程
            $duration = rand(10, 60);
            sleep($duration);

            $metrics = [
                'accuracy' => round(0.85 + mt_rand(-10, 10) / 100, 4),
                'precision' => round(0.82 + mt_rand(-10, 10) / 100, 4),
                'recall' => round(0.80 + mt_rand(-10, 10) / 100, 4),
                'f1' => round(0.83 + mt_rand(-10, 10) / 100, 4),
                'loss' => round(0.15 + mt_rand(-5, 5) / 100, 4),
            ];

            $job->update([
                'status' => 'completed',
                'results' => $metrics,
                'duration_seconds' => $duration,
                'completed_at' => now(),
            ]);

            // 创建模型版本
            MlModelVersion::create([
                'ml_model_id' => $job->ml_model_id,
                'version' => $this->incrementVersion($job->ml_model_id),
                'file_path' => "ml-models/{$job->ml_model_id}/auto-{$job->job_id}.bin",
                'file_hash' => Str::random(64),
                'file_size' => rand(1000000, 50000000),
                'metrics' => $metrics,
                'hyperparameters' => $job->config,
                'status' => 'staging',
            ]);

            Log::info('ML训练完成', ['job_id' => $job->job_id, 'metrics' => $metrics]);
        } catch (\Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            Log::error('ML训练失败', ['job_id' => $job->job_id, 'error' => $e->getMessage()]);
        }
    }

    protected function incrementVersion(int $mlModelId): string
    {
        $latest = MlModelVersion::where('ml_model_id', $mlModelId)
            ->orderByDesc('id')->first();
        if ($latest) {
            $parts = explode('.', $latest->version);
            $parts[2] = (int)$parts[2] + 1;
            return implode('.', $parts);
        }
        return 'v1.0.0';
    }

    // ═══════ 漂移监控 ═══════

    /**
     * 获取漂移事件列表
     */
    public function listDriftEvents(array $filters = []): array
    {
        $query = MlDriftEvent::with('modelVersion.model');

        if (!empty($filters['ml_model_id'])) {
            $query->whereHas('modelVersion', function ($q) use ($filters) {
                $q->where('ml_model_id', $filters['ml_model_id']);
            });
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['metric'])) {
            $query->where('metric', $filters['metric']);
        }
        if (!empty($filters['from'])) {
            $query->where('detected_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('detected_at', '<=', $filters['to']);
        }

        $perPage = $filters['per_page'] ?? 20;
        $results = $query->orderByDesc('detected_at')->paginate($perPage)->withQueryString();

        return $results->toArray();
    }

    /**
     * 检测模型漂移
     */
    public function detectDrift(int $mlModelVersionId, array $currentMetrics): ?MlDriftEvent
    {
        $version = MlModelVersion::with('model')->findOrFail($mlModelVersionId);

        if (!$version->metrics) {
            return null;
        }

        $baseline = $version->metrics;
        $driftThreshold = config('mlops.monitoring.drift.drift_threshold', 0.1);
        $mostSevere = null;

        foreach (config('mlops.monitoring.drift.metrics', ['accuracy', 'precision', 'recall', 'f1']) as $metric) {
            $baselineValue = $baseline[$metric] ?? null;
            $currentValue = $currentMetrics[$metric] ?? null;

            if ($baselineValue === null || $currentValue === null) {
                continue;
            }

            $driftValue = abs($currentValue - $baselineValue);

            if ($driftValue > $driftThreshold) {
                $severity = $driftValue > $driftThreshold * 2 ? 'critical' : ($driftValue > $driftThreshold * 1.5 ? 'warning' : 'info');

                $event = MlDriftEvent::create([
                    'ml_model_version_id' => $mlModelVersionId,
                    'metric' => $metric,
                    'baseline_value' => $baselineValue,
                    'current_value' => $currentValue,
                    'drift_value' => $driftValue,
                    'severity' => $severity,
                    'auto_retrain_triggered' => false,
                ]);

                $mostSevere = $event;

                // 严重漂移自动触发重训练
                if ($severity === 'critical' && config('mlops.auto_retrain.enabled')) {
                    $this->triggerAutoRetrain($version->model, $event);
                }
            }
        }

        return $mostSevere;
    }

    /**
     * 触发自动重训练
     */
    protected function triggerAutoRetrain(MlModel $model, MlDriftEvent $event): void
    {
        // 检查每日重训练次数限制
        $todayCount = MlTrainingJob::where('ml_model_id', $model->id)
            ->whereDate('created_at', today())
            ->count();

        $maxPerDay = config('mlops.auto_retrain.max_retrain_per_day', 2);
        if ($todayCount >= $maxPerDay) {
            Log::warning('自动重训练跳过：已达每日上限', [
                'model_id' => $model->id,
                'today_count' => $todayCount,
            ]);
            return;
        }

        $job = $this->submitTrainingJob($model->id, [
            'auto_retrain' => true,
            'trigger_reason' => "drift:{$event->metric}={$event->drift_value}",
        ]);

        $event->update(['auto_retrain_triggered' => true]);

        Log::info('自动重训练已触发', [
            'model_id' => $model->id,
            'event_id' => $event->id,
            'job_id' => $job->id,
        ]);
    }

    /**
     * 获取漂移统计摘要
     */
    public function getDriftSummary(int $tenantId, ?int $mlModelId = null): array
    {
        $query = MlDriftEvent::whereHas('modelVersion.model', function ($q) use ($tenantId, $mlModelId) {
            $q->where('tenant_id', $tenantId);
            if ($mlModelId) {
                $q->where('id', $mlModelId);
            }
        });

        return [
            'total_events' => (clone $query)->count(),
            'critical_events' => (clone $query)->where('severity', 'critical')->count(),
            'warning_events' => (clone $query)->where('severity', 'warning')->count(),
            'auto_retrain_triggered' => (clone $query)->where('auto_retrain_triggered', true)->count(),
            'recent_events' => (clone $query)->orderByDesc('detected_at')->take(5)->get(),
            'by_metric' => (clone $query)->selectRaw('metric, COUNT(*) as count')
                ->groupBy('metric')->pluck('count', 'metric')->toArray(),
            'by_severity' => (clone $query)->selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')->pluck('count', 'severity')->toArray(),
        ];
    }

    // ═══════ 仪表盘 ═══════

    /**
     * 获取MLOps仪表盘数据
     */
    public function getDashboard(int $tenantId): array
    {
        $models = MlModel::where('tenant_id', $tenantId)->withCount('versions')->get();

        return [
            'total_models' => $models->count(),
            'active_models' => $models->where('status', 'active')->count(),
            'total_versions' => $models->sum('versions_count'),
            'production_versions' => MlModelVersion::whereHas('model', fn($q) => $q->where('tenant_id', $tenantId))
                ->where('status', 'production')->count(),
            'recent_training' => MlTrainingJob::whereHas('model', fn($q) => $q->where('tenant_id', $tenantId))
                ->orderByDesc('id')->take(5)->get(),
            'drift_summary' => $this->getDriftSummary($tenantId),
            'by_framework' => $models->groupBy('framework')->map(fn($g) => $g->count())->toArray(),
            'by_task_type' => $models->groupBy('task_type')->map(fn($g) => $g->count())->toArray(),
        ];
    }
}
