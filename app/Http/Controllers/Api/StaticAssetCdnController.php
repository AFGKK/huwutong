<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\StaticAssetCdnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 静态资源 CDN 加速管理控制器 (M2-133)
 *
 * 提供前端构建产物的 CDN 部署、版本管理、回滚等管理接口。
 */
class StaticAssetCdnController extends Controller
{
    public function __construct(
        protected StaticAssetCdnService $cdnService,
    ) {}

    /**
     * CDN 部署统计和状态
     *
     * GET /api/static-assets/cdn/stats
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getStats());
    }

    /**
     * 部署当前构建产物到 CDN
     *
     * POST /api/static-assets/cdn/deploy
     */
    public function deploy(Request $request): JsonResponse
    {
        $version = $request->input('version');

        try {
            $result = $this->cdnService->deploy($version);
            return ApiResponse::success($result, __('app.static_asset_cdn.deployed', ['count' => $result['total']]));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('DEPLOY_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 激活指定版本
     *
     * POST /api/static-assets/cdn/activate
     */
    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'nullable|string|max:30',
        ]);

        try {
            $result = $this->cdnService->activateVersion($validated['version'] ?? null);
            return ApiResponse::success($result, __('app.static_asset_cdn.version_activated', ['version' => $result['version']]));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('ACTIVATE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 回滚到指定版本
     *
     * POST /api/static-assets/cdn/rollback
     */
    public function rollback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'required|string|max:30',
        ]);

        try {
            $result = $this->cdnService->rollback($validated['version']);
            return ApiResponse::success($result, __('app.static_asset_cdn.version_rolled_back', ['version' => $result['version']]));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('ROLLBACK_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 版本列表
     *
     * GET /api/static-assets/cdn/versions
     */
    public function versions(): JsonResponse
    {
        $versions = $this->cdnService->listDeployedVersions();

        return ApiResponse::success([
            'current_version' => $this->cdnService->getCurrentVersion(),
            'versions' => $versions,
            'total' => count($versions),
        ]);
    }

    /**
     * 获取当前版本详情
     *
     * GET /api/static-assets/cdn/version/current
     */
    public function currentVersion(): JsonResponse
    {
        $version = $this->cdnService->getCurrentVersion();
        $manifest = $this->cdnService->getManifest($version);
        $baseUrl = $this->cdnService->getAssetBaseUrl($version);

        return ApiResponse::success([
            'version' => $version,
            'base_url' => $baseUrl,
            'manifest' => $manifest,
        ]);
    }

    /**
     * 删除版本
     *
     * DELETE /api/static-assets/cdn/versions/{version}
     */
    public function destroyVersion(string $version): JsonResponse
    {
        try {
            $this->cdnService->deleteVersion($version);
            return ApiResponse::success(null, __('app.static_asset_cdn.version_deleted', ['version' => $version]));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('DELETE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 获取本地构建产物文件列表
     *
     * GET /api/static-assets/build-files
     */
    public function buildFiles(): JsonResponse
    {
        $files = $this->cdnService->getBuildFiles();

        return ApiResponse::success([
            'files' => $files,
            'total' => count($files),
        ]);
    }
}
