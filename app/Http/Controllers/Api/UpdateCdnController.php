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
        return ApiResponse::success($this->cdnService->getDashboard(), __('app.common.fetch_success'));
    }

    /** CDN 配置 */
    public function config(): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getCdnConfig(), __('app.common.fetch_success'));
    }

    /** 带宽统计 */
    public function bandwidth(): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getBandwidthStats(), __('app.common.fetch_success'));
    }

    /** 下载日志 */
    public function downloads(Request $request): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getDownloadLogs($request->all()), __('app.common.fetch_success'));
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

        return ApiResponse::success($result, $result['success'] ? __('app.update_cdn.cdn') : __("app.update_cdn.msg_7da4d00f"));
    }

    /** 发布时刷新并获取 CDN URL */
    public function publishAndPurge(Request $request, UpdatePackage $updatePackage): JsonResponse
    {
        $this->cdnService->purgeOnPublish($updatePackage);
        $urls = $this->cdnService->getPackageUrls($updatePackage);
        return ApiResponse::success([
            'package' => $updatePackage->fresh(),
            'cdn_urls' => $urls,
        ], __('app.update_cdn.cdn'));
    }

    /** 获取包的分块信息（断点续传） */
    public function chunkInfo(UpdatePackage $updatePackage): JsonResponse
    {
        return ApiResponse::success($this->cdnService->getChunkInfo($updatePackage), __('app.common.fetch_success'));
    }

    /** 获取包的 CDN URL 列表 */
    public function packageUrls(UpdatePackage $updatePackage): JsonResponse
    {
        return ApiResponse::success([
            'urls' => $this->cdnService->getPackageUrls($updatePackage),
            'download_url' => app(\App\Services\UpdateDistributionService::class)->getDownloadUrl($updatePackage),
        ], __('app.common.fetch_success'));
    }
}
