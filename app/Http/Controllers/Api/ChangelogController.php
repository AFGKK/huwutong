<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ApiChangelog;
use App\Models\ApiVersion;
use App\Services\ChangelogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    public function __construct(
        private readonly ChangelogService $changelogService
    ) {}

    /**
     * 获取 Changelog 列表
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['version', 'type', 'search', 'start_date', 'end_date']);
        $perPage = $request->input('per_page', 20);

        $result = $this->changelogService->list($filters, $perPage);

        return ApiResponse::success($result);
    }

    /**
     * 获取单个 Changelog
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success($this->changelogService->find($id));
    }

    /**
     * 创建 Changelog
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'required|string|max:50',
            'release_date' => 'nullable|date',
            'type' => 'nullable|string|in:release,beta,hotfix,security',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'affected_endpoints' => 'nullable|array',
            'migration_guide' => 'nullable|string',
            'source' => 'nullable|string|in:manual,auto_detect,git',
        ]);

        $changelog = $this->changelogService->create($validated);

        return ApiResponse::success($changelog, 201);
    }

    /**
     * 更新 Changelog
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $changelog = ApiChangelog::findOrFail($id);

        $validated = $request->validate([
            'version' => 'sometimes|string|max:50',
            'release_date' => 'nullable|date',
            'type' => 'nullable|string|in:release,beta,hotfix,security',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'affected_endpoints' => 'nullable|array',
            'migration_guide' => 'nullable|string',
        ]);

        return ApiResponse::success($this->changelogService->update($changelog, $validated));
    }

    /**
     * 删除 Changelog
     */
    public function destroy(int $id): JsonResponse
    {
        $changelog = ApiChangelog::findOrFail($id);
        $this->changelogService->delete($changelog);

        return ApiResponse::success(null, 204);
    }

    /**
     * 统计概览
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->changelogService->stats());
    }

    /**
     * 自动检测端点变更并生成 Changelog
     */
    public function autoGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_version_id' => 'required|integer|exists:api_versions,id',
        ]);

        $result = $this->changelogService->autoGenerate($validated['api_version_id']);

        return ApiResponse::success($result);
    }

    /**
     * 创建端点快照
     */
    public function createSnapshot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_version_id' => 'required|integer|exists:api_versions,id',
            'version_label' => 'nullable|string|max:100',
        ]);

        $label = $validated['version_label'] ?? null;
        $apiVersion = ApiVersion::findOrFail($validated['api_version_id']);

        if (!$label) {
            $label = $apiVersion->version . '-snapshot-' . now()->format('Ymd');
        }

        $count = $this->changelogService->createSnapshot($validated['api_version_id'], $label);

        return ApiResponse::success([
            'snapshot_version' => $label,
            'endpoints_snapshotted' => $count,
        ]);
    }

    /**
     * 获取自动检测历史
     */
    public function autoDetectHistory(): JsonResponse
    {
        return ApiResponse::success($this->changelogService->getAutoDetectionHistory());
    }

    /**
     * 生成大版本迁移指南
     */
    public function migrationGuide(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_version' => 'required|string|max:50',
            'to_version' => 'required|string|max:50',
        ]);

        $result = $this->changelogService->generateMigrationGuide(
            $validated['from_version'],
            $validated['to_version']
        );

        return ApiResponse::success($result);
    }

    // ─── 公开 API（无需认证） ───

    /**
     * 公开 Changelog 列表（仅已发布）
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $perPage = min($request->input('per_page', 20), 50);

        $query = ApiChangelog::whereNotNull('release_date')
            ->where('release_date', '<=', now())
            ->orderByDesc('release_date')
            ->orderByDesc('created_at');

        return ApiResponse::success($query->paginate($perPage));
    }

    /**
     * 公开最新 Changelog
     */
    public function publicLatest(): JsonResponse
    {
        $latest = ApiChangelog::whereNotNull('release_date')
            ->where('release_date', '<=', now())
            ->orderByDesc('release_date')
            ->first();

        return ApiResponse::success($latest);
    }

    /**
     * 按版本分组的公开 Changelog
     */
    public function publicByVersion(): JsonResponse
    {
        $changelogs = ApiChangelog::whereNotNull('release_date')
            ->where('release_date', '<=', now())
            ->orderByDesc('release_date')
            ->get()
            ->groupBy('version');

        $result = [];
        foreach ($changelogs as $version => $items) {
            $result[] = [
                'version' => $version,
                'changelogs' => $items,
                'total' => $items->count(),
                'latest_release' => $items->first()->release_date,
            ];
        }

        return ApiResponse::success($result);
    }
}
