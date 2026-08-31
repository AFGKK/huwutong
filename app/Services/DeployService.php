<?php

namespace App\Services;

use App\Models\DeployEnvironment;
use App\Models\DeployJob;
use App\Models\DeployRelease;
use Illuminate\Support\Facades\DB;

/**
 * DevOps 自动化部署管道服务 (M3-37)
 *
 * 核心功能：
 * 1. 环境管理（production/staging/development）
 * 2. 发布/版本管理（语义版本号、Git关联）
 * 3. 部署作业流水线（执行、回滚、日志）
 * 4. 部署看板统计
 */
class DeployService
{
    // ═══════ 环境管理 ═══════

    public function listEnvironments(int $tenantId): array
    {
        return DeployEnvironment::where('tenant_id', $tenantId)
            ->withCount('deployJobs')
            ->orderBy('is_protected')
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    public function createEnvironment(array $data): DeployEnvironment
    {
        return DeployEnvironment::create($data);
    }

    public function updateEnvironment(DeployEnvironment $env, array $data): DeployEnvironment
    {
        $env->update($data);
        return $env->fresh();
    }

    public function deleteEnvironment(DeployEnvironment $env): void
    {
        $env->delete();
    }

    // ═══════ 发布管理 ═══════

    public function listReleases(int $tenantId, array $filters = []): array
    {
        $query = DeployRelease::where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('version', 'like', "%{$filters['search']}%")
                  ->orWhere('code_name', 'like', "%{$filters['search']}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 15)
            ->withQueryString()
            ->toArray();
    }

    public function createRelease(array $data): DeployRelease
    {
        return DeployRelease::create($data);
    }

    public function updateRelease(DeployRelease $release, array $data): DeployRelease
    {
        $release->update($data);
        return $release->fresh();
    }

    public function deleteRelease(DeployRelease $release): void
    {
        $release->delete();
    }

    /**
     * 触发部署作业
     */
    public function triggerDeploy(int $tenantId, array $data): DeployJob
    {
        $release = DeployRelease::findOrFail($data['deploy_release_id']);
        $env = DeployEnvironment::findOrFail($data['deploy_environment_id']);

        // 检查受保护环境
        if ($env->is_protected && config('app.env') !== 'production') {
            throw new \RuntimeException(__('app.deploy_service.protected_env_error', ['name' => $env->name]));
        }

        $steps = match ($data['type'] ?? 'full') {
            'full' => $this->getFullDeploySteps(),
            'backend_only' => $this->getBackendDeploySteps(),
            'frontend_only' => $this->getFrontendDeploySteps(),
            default => $this->getFullDeploySteps(),
        };

        $job = DeployJob::create([
            'tenant_id' => $tenantId,
            'deploy_environment_id' => $env->id,
            'deploy_release_id' => $release->id,
            'type' => $data['type'] ?? 'full',
            'status' => 'running',
            'steps' => $steps,
            'triggered_by' => $data['triggered_by'] ?? __('app.deploy_service.system_trigger'),
            'started_at' => now(),
        ]);

        // 更新发布状态
        $release->update(['status' => 'building']);

        return $job->fresh();
    }

    /**
     * 部署完成回调
     */
    public function completeDeploy(DeployJob $job, bool $success, ?string $output = null, ?string $error = null): DeployJob
    {
        $status = $success ? 'success' : 'failed';

        $job->update([
            'status' => $status,
            'output' => $output,
            'error_message' => $error,
            'completed_at' => now(),
        ]);

        // 更新发布状态
        $release = $job->release;
        if ($release) {
            $release->update([
                'status' => $success ? 'deployed' : 'failed',
                'deployed_at' => $success ? now() : null,
            ]);
        }

        return $job->fresh();
    }

    /**
     * 回滚部署
     */
    public function rollbackDeploy(DeployJob $job): DeployJob
    {
        $job->update([
            'status' => 'rolling_back',
            'output' => ($job->output ?? '') . "\n" . __('app.deploy_service.rollback_started') . "\n",
        ]);

        // 创建回滚作业
        $rollbackJob = DeployJob::create([
            'tenant_id' => $job->tenant_id,
            'deploy_environment_id' => $job->deploy_environment_id,
            'deploy_release_id' => $job->deploy_release_id,
            'type' => 'rollback',
            'status' => 'running',
            'steps' => [
                ['name' => __('app.deploy_service.step_confirm_rollback_version'), 'status' => 'pending', 'duration_ms' => 0],
                ['name' => __('app.deploy_service.step_execute_rollback_script'), 'status' => 'pending', 'duration_ms' => 0],
                ['name' => __('app.deploy_service.step_verify_rollback'), 'status' => 'pending', 'duration_ms' => 0],
            ],
            'triggered_by' => __('app.deploy_service.system_auto_rollback'),
            'started_at' => now(),
        ]);

        // 标记原作业为已回滚
        $job->update(['status' => 'rolled_back']);

        // 更新发布状态
        $release = $job->release;
        if ($release) {
            $release->update([
                'status' => 'rolled_back',
                'rolled_back_at' => now(),
            ]);
        }

        // 模拟完成回滚
        $rollbackJob->update([
            'status' => 'success',
            'output' => __('app.deploy_service.rollback_completed'),
            'completed_at' => now(),
        ]);

        return $rollbackJob->fresh();
    }

    // ═══════ 部署作业管理 ═══════

    public function listJobs(int $tenantId, array $filters = []): array
    {
        $query = DeployJob::where('deploy_jobs.tenant_id', $tenantId)
            ->with(['environment:id,name,slug', 'release:id,version,code_name'])
            ->orderByDesc('deploy_jobs.created_at');

        if (!empty($filters['status'])) {
            $query->where('deploy_jobs.status', $filters['status']);
        }

        if (!empty($filters['environment_id'])) {
            $query->where('deploy_jobs.deploy_environment_id', $filters['environment_id']);
        }

        return $query->paginate($filters['per_page'] ?? 20)
            ->withQueryString()
            ->toArray();
    }

    public function getJobDetail(int $tenantId, int $jobId): ?DeployJob
    {
        return DeployJob::where('tenant_id', $tenantId)
            ->with(['environment', 'release'])
            ->find($jobId);
    }

    // ═══════ 仪表盘 ═══════

    public function getDashboardStats(int $tenantId): array
    {
        $envCount = DeployEnvironment::where('tenant_id', $tenantId)->count();
        $totalReleases = DeployRelease::where('tenant_id', $tenantId)->count();
        $deployedReleases = DeployRelease::where('tenant_id', $tenantId)
            ->where('status', 'deployed')->count();

        $recentJobs = DeployJob::where('deploy_jobs.tenant_id', $tenantId)
            ->with(['environment:id,name', 'release:id,version'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->toArray();

        $successRate = DeployJob::where('deploy_jobs.tenant_id', $tenantId)
            ->whereIn('status', ['success', 'failed'])
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count")
            ->first();

        // 各环境最新部署
        $envs = DeployEnvironment::where('tenant_id', $tenantId)->pluck('id');
        $latestDeployments = [];
        foreach ($envs as $envId) {
            $latestJob = DeployJob::where('deploy_jobs.tenant_id', $tenantId)
                ->where('deploy_jobs.deploy_environment_id', $envId)
                ->with(['release:id,version', 'environment:id,name'])
                ->orderByDesc('created_at')
                ->first();
            if ($latestJob) {
                $latestDeployments[] = $latestJob->toArray();
            }
        }

        return [
            'environment_count' => $envCount,
            'total_releases' => $totalReleases,
            'deployed_releases' => $deployedReleases,
            'total_jobs' => DeployJob::where('tenant_id', $tenantId)->count(),
            'success_rate' => $successRate && $successRate->total > 0
                ? round(($successRate->success_count / $successRate->total) * 100, 1)
                : 0,
            'recent_jobs' => $recentJobs,
            'latest_deployments' => $latestDeployments,
        ];
    }

    // ═══════ 部署步骤模板 ═══════

    protected function getFullDeploySteps(): array
    {
        return [
            ['name' => __('app.deploy_service.step_pull_code'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_install_deps'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_backend_build'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_frontend_build'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_db_migration'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_cache_clear'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_service_restart'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_health_check'), 'status' => 'pending', 'duration_ms' => 0],
        ];
    }

    protected function getBackendDeploySteps(): array
    {
        return [
            ['name' => __('app.deploy_service.step_pull_code'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_install_composer'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_db_migration'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_cache_clear'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_restart_php_fpm'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_health_check'), 'status' => 'pending', 'duration_ms' => 0],
        ];
    }

    protected function getFrontendDeploySteps(): array
    {
        return [
            ['name' => __('app.deploy_service.step_pull_code'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_install_npm'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_build_vite'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_cdn_push'), 'status' => 'pending', 'duration_ms' => 0],
            ['name' => __('app.deploy_service.step_cdn_cache_refresh'), 'status' => 'pending', 'duration_ms' => 0],
        ];
    }
}
