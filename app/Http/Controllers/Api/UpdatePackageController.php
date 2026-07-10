<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\UpdatePackage;
use App\Services\UpdateDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UpdatePackageController extends Controller
{
    public function __construct(
        protected UpdateDistributionService $updateService,
    ) {}

    /**
     * 获取产品的更新包列表
     */
    public function index(Request $request, Product $product): JsonResponse
    {
        $this->authorize('viewAny', UpdatePackage::class);

        $query = $product->updatePackages()->with('creator:id,name');

        // 筛选状态
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 筛选类型
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $packages = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $packages,
        ]);
    }

    /**
     * 获取单个更新包详情
     */
    public function show(UpdatePackage $updatePackage): JsonResponse
    {
        $this->authorize('view', $updatePackage);

        $updatePackage->load(['product:id,name', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'data' => [
                ...$updatePackage->toArray(),
                'download_url' => $updatePackage->downloadUrl(),
                'file_size_human' => $updatePackage->fileSizeForHumans(),
            ],
        ]);
    }

    /**
     * 上传并创建更新包
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorize('create', UpdatePackage::class);

        $validator = Validator::make($request->all(), [
            'version' => 'required|string|max:32',
            'file' => 'required|file|max:' . (1024 * 1024), // 默认最大 1GB
            'type' => 'sometimes|in:full,incremental,hotfix',
            'prev_version' => 'sometimes|nullable|string|max:32',
            'release_notes' => 'sometimes|json',
            'metadata' => 'sometimes|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $package = $this->updateService->uploadPackage(
                $product,
                $request->input('version'),
                $request->file('file'),
                [
                    'type' => $request->input('type', 'full'),
                    'prev_version' => $request->input('prev_version'),
                    'release_notes' => json_decode($request->input('release_notes', '{}'), true),
                    'metadata' => json_decode($request->input('metadata', '{}'), true),
                ],
            );

            return response()->json([
                'success' => true,
                'message' => '更新包上传成功',
                'data' => $package->load('product:id,name'),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '上传失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 发布更新包
     */
    public function publish(UpdatePackage $updatePackage): JsonResponse
    {
        $this->authorize('update', $updatePackage);

        if ($updatePackage->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能发布草稿状态的更新包',
            ], 422);
        }

        $this->updateService->publishPackage($updatePackage);

        return response()->json([
            'success' => true,
            'message' => '更新包已发布',
            'data' => $updatePackage->fresh(),
        ]);
    }

    /**
     * 废弃更新包
     */
    public function deprecate(UpdatePackage $updatePackage): JsonResponse
    {
        $this->authorize('update', $updatePackage);

        if (!in_array($updatePackage->status, ['published', 'draft'])) {
            return response()->json([
                'success' => false,
                'message' => '当前状态不允许废弃',
            ], 422);
        }

        $this->updateService->deprecatePackage($updatePackage);

        return response()->json([
            'success' => true,
            'message' => '更新包已废弃',
            'data' => $updatePackage->fresh(),
        ]);
    }

    /**
     * 删除更新包
     */
    public function destroy(UpdatePackage $updatePackage): JsonResponse
    {
        $this->authorize('delete', $updatePackage);

        $this->updateService->deletePackage($updatePackage);

        return response()->json([
            'success' => true,
            'message' => '更新包已删除',
        ]);
    }

    /**
     * 下载更新包
     */
    public function download(UpdatePackage $updatePackage): \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($updatePackage->status !== 'published') {
            abort(404, '更新包未发布');
        }

        // 记录下载
        $this->updateService->recordDownload($updatePackage);

        // 重定向到 CDN URL（302 临时跳转）
        $url = $this->updateService->getDownloadUrl($updatePackage);
        return redirect()->away($url);
    }

    /**
     * 检查产品的最新可用更新
     */
    public function checkUpdate(Request $request, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_version' => 'required|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $currentVersion = $request->input('current_version');
        $latest = $this->updateService->getLatestVersion($product, $currentVersion);

        return response()->json([
            'success' => true,
            'data' => [
                'has_update' => $latest !== null,
                'latest_version' => $latest?->version,
                'latest_package_id' => $latest?->id,
                'download_url' => $latest?->downloadUrl(),
                'release_notes' => $latest?->release_notes,
                'file_size' => $latest?->file_size,
                'published_at' => $latest?->published_at,
            ],
        ]);
    }

    /**
     * 获取下载统计
     */
    public function downloadStats(UpdatePackage $updatePackage): JsonResponse
    {
        $this->authorize('view', $updatePackage);

        $total = $updatePackage->downloads()->count();
        $today = $updatePackage->downloads()
            ->whereDate('downloaded_at', today())
            ->count();

        $topRegions = $updatePackage->downloads()
            ->selectRaw(db_substring_index('client_ip', '.', 3).' as subnet, COUNT(*) as count')
            ->groupBy('subnet')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_downloads' => $total,
                'today_downloads' => $today,
                'top_subnets' => $topRegions,
            ],
        ]);
    }
}
