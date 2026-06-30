<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SdkVersionManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-16 SDK版本兼容策略 API
 */
class SdkVersionController extends Controller
{
    public function __construct(
        private readonly SdkVersionManagerService $sdkVersionManager,
    ) {}

    /**
     * 版本概览仪表盘
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->sdkVersionManager->getDashboard(),
        ]);
    }

    /**
     * 获取所有语言最新列表
     */
    public function index(): JsonResponse
    {
        $languages = config('sdk-version.compatibility.api_versions', []);
        $data = [];

        foreach (\App\Models\SdkVersion::LANGUAGES as $lang) {
            $data[$lang] = $this->sdkVersionManager->getVersions($lang);
        }

        return response()->json([
            'code' => 0,
            'data' => $data,
        ]);
    }

    /**
     * 获取某语言的版本列表
     */
    public function languageVersions(string $language): JsonResponse
    {
        if (!in_array($language, \App\Models\SdkVersion::LANGUAGES)) {
            return response()->json(['code' => 1, 'message' => '不支持的语言'], 422);
        }

        return response()->json([
            'code' => 0,
            'data' => $this->sdkVersionManager->getVersions($language),
        ]);
    }

    /**
     * 注册新版本
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'required|string|in:' . implode(',', \App\Models\SdkVersion::LANGUAGES),
            'version' => 'required|string|max:20',
            'stage' => 'nullable|string|in:preview,stable,deprecated,sunset',
            'is_current' => 'nullable|boolean',
            'min_api_version' => 'nullable|string|max:10',
            'changelog' => 'nullable|string',
            'upgrade_notes' => 'nullable|string',
            'compatible_sdk_versions' => 'nullable|string|max:200',
            'released_at' => 'nullable|date',
        ]);

        $version = $this->sdkVersionManager->registerVersion($validated);

        return response()->json([
            'code' => 0,
            'message' => '版本注册成功',
            'data' => $version,
        ]);
    }

    /**
     * 版本详情
     */
    public function show(int $id): JsonResponse
    {
        $version = \App\Models\SdkVersion::findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $version,
        ]);
    }

    /**
     * 更新版本信息
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $version = \App\Models\SdkVersion::findOrFail($id);

        $validated = $request->validate([
            'stage' => 'nullable|string|in:preview,stable,deprecated,sunset',
            'is_current' => 'nullable|boolean',
            'allow_production' => 'nullable|boolean',
            'min_api_version' => 'nullable|string|max:10',
            'changelog' => 'nullable|string',
            'upgrade_notes' => 'nullable|string',
            'compatible_sdk_versions' => 'nullable|string|max:200',
        ]);

        $version->update($validated);

        // 如果标记为当前版本，取消同语言其他版本的 is_current
        if (!empty($validated['is_current'])) {
            \App\Models\SdkVersion::byLanguage($version->language)
                ->where('id', '!=', $version->id)
                ->update(['is_current' => false]);
        }

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => $version->fresh(),
        ]);
    }

    /**
     * 检查版本升级
     */
    public function checkUpgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'required|string|in:' . implode(',', \App\Models\SdkVersion::LANGUAGES),
            'version' => 'required|string|max:20',
        ]);

        return response()->json([
            'code' => 0,
            'data' => $this->sdkVersionManager->checkUpgrade($validated['language'], $validated['version']),
        ]);
    }

    /**
     * 获取升级路径
     */
    public function upgradePath(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'required|string|in:' . implode(',', \App\Models\SdkVersion::LANGUAGES),
            'from_version' => 'required|string|max:20',
        ]);

        return response()->json([
            'code' => 0,
            'data' => [
                'path' => $this->sdkVersionManager->getUpgradePath($validated['language'], $validated['from_version']),
                'recommended' => $this->sdkVersionManager->getRecommendedVersion($validated['language']),
            ],
        ]);
    }

    /**
     * 获取迁移指南
     */
    public function migrationGuide(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'required|string|in:' . implode(',', \App\Models\SdkVersion::LANGUAGES),
            'target_version' => 'nullable|string|max:20',
        ]);

        return response()->json([
            'code' => 0,
            'data' => $this->sdkVersionManager->getMigrationGuide(
                $validated['language'],
                $validated['target_version'] ?? null
            ),
        ]);
    }

    /**
     * 标记版本为废弃
     */
    public function markDeprecated(int $id): JsonResponse
    {
        $version = $this->sdkVersionManager->markDeprecated($id);

        return response()->json([
            'code' => 0,
            'message' => "版本 {$version->version} 已标记为废弃，停服日期: {$version->sunset_at?->toDateString()}",
            'data' => $version,
        ]);
    }

    /**
     * 标记版本为停服
     */
    public function markSunset(int $id): JsonResponse
    {
        $version = $this->sdkVersionManager->markSunset($id);

        return response()->json([
            'code' => 0,
            'message' => "版本 {$version->version} 已停服",
            'data' => $version,
        ]);
    }

    /**
     * 批量注册默认版本（从 config/sdk-manager.php 导入初始版本）
     */
    public function seedDefaults(): JsonResponse
    {
        $versions = config('sdk-manager.versions', []);
        $created = 0;

        foreach ($versions as $language => $config) {
            $existing = \App\Models\SdkVersion::byLanguage($language)
                ->where('version', $config['version'])
                ->exists();

            if (!$existing) {
                $this->sdkVersionManager->registerVersion([
                    'language' => $language,
                    'version' => $config['version'],
                    'stage' => 'stable',
                    'is_current' => true,
                    'allow_production' => true,
                    'min_api_version' => 'v1',
                    'changelog' => "{$config['name']} 初始版本 {$config['version']}",
                    'released_at' => now(),
                ]);
                $created++;
            }
        }

        return response()->json([
            'code' => 0,
            'message' => "已导入 {$created} 个默认版本",
            'data' => ['created' => $created],
        ]);
    }

    /**
     * 处理过期废弃版本
     */
    public function processExpired(): JsonResponse
    {
        $count = $this->sdkVersionManager->processExpiredDeprecations();

        return response()->json([
            'code' => 0,
            'message' => "已处理 {$count} 个过期版本",
            'data' => ['processed' => $count],
        ]);
    }
}
