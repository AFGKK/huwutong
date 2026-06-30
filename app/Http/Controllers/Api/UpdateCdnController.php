<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\UpdatePackage;
use App\Services\UpdateCdnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 更新包 CDN 分发管理 (M2-69)
 */
class UpdateCdnController extends Controller
{
    public function __construct(
        protected UpdateCdnService $cdnService
    ) {}

    /** 仪表盘 */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getDashboard(), '获取成功');
    }

    /** CDN 配置 */
    public function config(): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getCdnConfig(), '获取成功');
    }

    /** 带宽统计 */
    public function bandwidth(): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getBandwidthStats(), '获取成功');
    }

    /** 下载日志 */
    public function downloads(Request $request): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getDownloadLogs($request->all()), '获取成功');
    }

    /** 刷新 CDN 缓存 */
    public function purge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => 'nullable|integer|exists:update_packages,id',
            'url' => 'nullable|string|url|max:500',
        ]);

        $package = null;
        if (!empty($validated['package_id'])) {
            $package = UpdatePackage::findOrFail($validated['package_id']);
        }

        $result = $this->cdnService->purgeCache(
            $validated['url'] ?? null,
            $package
        );

        return ApiResponse::success($result, $result['success'] ? 'CDN 缓存已刷新' : '部分刷新失败');
    }

    /** 发布时刷新并获取 CDN URL */
    public function publishAndPurge(Request $request, UpdatePackage $updatePackage): JsonResponse
    {
        $this->cdnService->purgeOnPublish($updatePackage);
        $urls = $this->cdnService->getPackageUrls($updatePackage);
        return ApiResponse::success([
            'package' => $updatePackage->fresh(),
            'cdn_urls' => $urls,
        ], '已发布并刷新 CDN 缓存');
    }

    /** 获取包的分块信息（断点续传） */
    public function chunkInfo(UpdatePackage $updatePackage): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getChunkInfo($updatePackage), '获取成功');
    }

    /** 获取包的 CDN URL 列表 */
    public function packageUrls(UpdatePackage $updatePackage): JsonResponse
    {
        return ApiResponse::success([
            'urls' => $this->cdnService->getPackageUrls($updatePackage),
            'download_url' => app(\App\Services\UpdateDistributionService::class)->getDownloadUrl($updatePackage),
        ], '获取成功');
    }
}
