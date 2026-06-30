<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DeployEnvironment;
use App\Models\DeployJob;
use App\Models\DeployRelease;
use App\Services\DeployService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeployController extends Controller
{
    public function __construct(
        protected DeployService $service
    ) {}

    // ─── 环境管理 ───

    public function environments(Request $request)
    {
        return ApiResponse::success($this->service->listEnvironments($request->user()->tenant_id));
    }

    public function storeEnvironment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100',
            'description' => 'nullable|string',
            'base_url' => 'nullable|string|max:500',
            'server_type' => 'nullable|string|in:self-hosted,cloud,kubernetes',
            'is_protected' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->service->createEnvironment($data), 201);
    }

    public function updateEnvironment(Request $request, DeployEnvironment $deployEnvironment)
    {
        return ApiResponse::success($this->service->updateEnvironment($deployEnvironment, $request->all()));
    }

    public function deleteEnvironment(DeployEnvironment $deployEnvironment)
    {
        $this->service->deleteEnvironment($deployEnvironment);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 发布管理 ───

    public function releases(Request $request)
    {
        return ApiResponse::success(
            $this->service->listReleases(
                $request->user()->tenant_id,
                $request->only(['status', 'search', 'per_page'])
            )
        );
    }

    public function storeRelease(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'version' => 'required|string|max:50',
            'code_name' => 'nullable|string|max:200',
            'changelog' => 'nullable|string',
            'git_branch' => 'nullable|string|max:200',
            'git_commit_hash' => 'nullable|string|max:40',
            'git_commit_message' => 'nullable|string|max:500',
            'author' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->service->createRelease($data), 201);
    }

    public function updateRelease(Request $request, DeployRelease $deployRelease)
    {
        return ApiResponse::success($this->service->updateRelease($deployRelease, $request->all()));
    }

    public function deleteRelease(DeployRelease $deployRelease)
    {
        $this->service->deleteRelease($deployRelease);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 部署作业 ───

    public function jobs(Request $request)
    {
        return ApiResponse::success(
            $this->service->listJobs(
                $request->user()->tenant_id,
                $request->only(['status', 'environment_id', 'per_page'])
            )
        );
    }

    public function jobDetail(Request $request, DeployJob $deployJob)
    {
        return ApiResponse::success(
            $this->service->getJobDetail($request->user()->tenant_id, $deployJob->id)
        );
    }

    public function triggerDeploy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deploy_release_id' => 'required|integer|exists:deploy_releases,id',
            'deploy_environment_id' => 'required|integer|exists:deploy_environments,id',
            'type' => 'nullable|string|in:full,backend_only,frontend_only',
            'triggered_by' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['triggered_by'] ??= 'Web UI';

        return ApiResponse::success(
            $this->service->triggerDeploy($request->user()->tenant_id, $data)
        );
    }

    public function completeDeploy(Request $request, DeployJob $deployJob)
    {
        return ApiResponse::success(
            $this->service->completeDeploy(
                $deployJob,
                $request->input('success', true),
                $request->input('output'),
                $request->input('error_message')
            )
        );
    }

    public function rollbackDeploy(DeployJob $deployJob)
    {
        return ApiResponse::success($this->service->rollbackDeploy($deployJob));
    }

    // ─── 仪表盘 ───

    public function dashboard(Request $request)
    {
        return ApiResponse::success($this->service->getDashboardStats($request->user()->tenant_id));
    }
}
