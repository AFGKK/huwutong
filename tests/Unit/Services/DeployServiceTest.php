<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\DeployEnvironment;
use App\Models\DeployJob;
use App\Models\DeployRelease;
use App\Models\Tenant;
use App\Services\DeployService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeployServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DeployService $service;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(DeployService::class);
        $this->tenant = Tenant::factory()->create();
    }

    // ─── 环境管理 ───

    public function test_creates_and_lists_environments()
    {
        DeployEnvironment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Production',
            'slug' => 'production',
        ]);
        DeployEnvironment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Staging',
            'slug' => 'staging',
        ]);

        $envs = $this->service->listEnvironments($this->tenant->id);

        $this->assertCount(2, $envs);
        $this->assertEquals('Production', $envs[0]['name']);
    }

    public function test_updates_environment()
    {
        $env = DeployEnvironment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_protected' => true,
        ]);

        $updated = $this->service->updateEnvironment($env, ['is_protected' => false]);

        $this->assertFalse($updated->is_protected);
    }

    public function test_deletes_environment()
    {
        $env = DeployEnvironment::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->deleteEnvironment($env);

        $this->assertDatabaseMissing('deploy_environments', ['id' => $env->id]);
    }

    // ─── 发布管理 ───

    public function test_creates_and_lists_releases()
    {
        DeployRelease::factory()->create([
            'tenant_id' => $this->tenant->id,
            'version' => '2.5.0',
            'code_name' => '蓝色多瑙河',
        ]);
        DeployRelease::factory()->create([
            'tenant_id' => $this->tenant->id,
            'version' => '2.4.0',
            'code_name' => '月光',
        ]);

        $releases = $this->service->listReleases($this->tenant->id);

        $this->assertCount(2, $releases['data']);
    }

    public function test_filters_releases_by_status()
    {
        DeployRelease::factory()->create([
            'tenant_id' => $this->tenant->id,
            'version' => '1.0.0',
            'status' => 'deployed',
        ]);
        DeployRelease::factory()->create([
            'tenant_id' => $this->tenant->id,
            'version' => '1.1.0',
            'status' => 'failed',
        ]);

        $releases = $this->service->listReleases($this->tenant->id, ['status' => 'deployed']);

        $this->assertCount(1, $releases['data']);
        $this->assertEquals('1.0.0', $releases['data'][0]['version']);
    }

    public function test_delete_release()
    {
        $release = DeployRelease::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->deleteRelease($release);

        $this->assertDatabaseMissing('deploy_releases', ['id' => $release->id]);
    }

    // ─── 部署作业 ───

    public function test_triggers_deploy()
    {
        $env = DeployEnvironment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_protected' => false, // 非受保护环境
        ]);
        $release = DeployRelease::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $job = $this->service->triggerDeploy($this->tenant->id, [
            'deploy_environment_id' => $env->id,
            'deploy_release_id' => $release->id,
            'type' => 'full',
            'triggered_by' => '测试用户',
        ]);

        $this->assertEquals('running', $job->status);
        $this->assertEquals('full', $job->type);

        // 发布状态应更新为 building
        $release->refresh();
        $this->assertEquals('building', $release->status);
    }

    public function test_completes_deploy_successfully()
    {
        $job = DeployJob::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
        ]);

        $completed = $this->service->completeDeploy($job, true, '部署成功完成');

        $this->assertEquals('success', $completed->status);
        $this->assertNotNull($completed->completed_at);

        // 关联的发布应更新为 deployed
        $job->release->refresh();
        $this->assertEquals('deployed', $job->release->status);
    }

    public function test_completes_deploy_with_failure()
    {
        $job = DeployJob::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
        ]);

        $completed = $this->service->completeDeploy($job, false, null, '数据库迁移失败');

        $this->assertEquals('failed', $completed->status);
        $this->assertEquals('数据库迁移失败', $completed->error_message);

        $job->release->refresh();
        $this->assertEquals('failed', $job->release->status);
    }

    public function test_rollback_deploy()
    {
        // 创建一个已部署的作业
        $env = DeployEnvironment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_protected' => false,
        ]);
        $release = DeployRelease::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'deployed',
        ]);
        $job = DeployJob::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deploy_environment_id' => $env->id,
            'deploy_release_id' => $release->id,
            'status' => 'success',
        ]);

        $rollbackJob = $this->service->rollbackDeploy($job);

        $this->assertEquals('success', $rollbackJob->status);
        $this->assertEquals('rollback', $rollbackJob->type);

        // 原作业标记为已回滚
        $job->refresh();
        $this->assertEquals('rolled_back', $job->status);

        // 发布标记为已回滚
        $release->refresh();
        $this->assertEquals('rolled_back', $release->status);
    }

    // ─── 仪表盘 ───

    public function test_get_dashboard_stats()
    {
        $env = DeployEnvironment::factory()->create(['tenant_id' => $this->tenant->id]);
        $release1 = DeployRelease::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'deployed']);
        $release2 = DeployRelease::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'failed']);
        DeployJob::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deploy_environment_id' => $env->id,
            'deploy_release_id' => $release1->id,
            'status' => 'success',
        ]);
        DeployJob::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deploy_environment_id' => $env->id,
            'deploy_release_id' => $release2->id,
            'status' => 'failed',
        ]);

        $stats = $this->service->getDashboardStats($this->tenant->id);

        $this->assertEquals(1, $stats['environment_count']);
        $this->assertEquals(2, $stats['total_releases']);
        $this->assertEquals(1, $stats['deployed_releases']);
        $this->assertEquals(50.0, $stats['success_rate']); // 1/2 = 50%
        $this->assertCount(2, $stats['recent_jobs']);
    }

    // ─── 作业列表过滤 ───

    public function test_lists_jobs_with_filters()
    {
        $env1 = DeployEnvironment::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'prod']);
        $env2 = DeployEnvironment::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'stage']);

        DeployJob::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deploy_environment_id' => $env1->id,
            'status' => 'success',
        ]);
        DeployJob::factory()->create([
            'tenant_id' => $this->tenant->id,
            'deploy_environment_id' => $env2->id,
            'status' => 'failed',
        ]);

        $jobs = $this->service->listJobs($this->tenant->id, ['status' => 'success']);

        $this->assertCount(1, $jobs['data']);
        $this->assertEquals('success', $jobs['data'][0]['status']);
    }
}
