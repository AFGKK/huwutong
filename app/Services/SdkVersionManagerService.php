<?php

namespace App\Services;

use App\Models\SdkVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * M2-16 客户端SDK版本兼容策略
 *
 * 多版本共存、版本废弃、强制升级管理。
 * 依赖 M2-34 统一错误码。
 */
class SdkVersionManagerService
{
    private const CACHE_KEY_VERSIONS = 'sdk:versions:%s';
    private const CACHE_KEY_COMPATIBLE = 'sdk:compatible:%s:%s';
    private const CACHE_TTL = 3600;

    /**
     * 注册新版本
     */
    public function registerVersion(array $data): SdkVersion
    {
        $version = SdkVersion::create([
            'language' => $data['language'],
            'version' => $data['version'],
            'stage' => $data['stage'] ?? 'preview',
            'is_current' => $data['is_current'] ?? false,
            'allow_production' => $data['allow_production'] ?? ($data['stage'] === 'stable'),
            'min_api_version' => $data['min_api_version'] ?? 'v1',
            'changelog' => $data['changelog'] ?? null,
            'upgrade_notes' => $data['upgrade_notes'] ?? null,
            'compatible_sdk_versions' => $data['compatible_sdk_versions'] ?? null,
            'released_at' => $data['released_at'] ?? now(),
        ]);

        // 如果标记为当前版本，取消同语言其他版本的 is_current
        if ($version->is_current) {
            SdkVersion::byLanguage($version->language)
                ->where('id', '!=', $version->id)
                ->update(['is_current' => false]);
        }

        $this->clearCache($version->language);
        return $version;
    }

    /**
     * 获取某语言的所有版本
     */
    public function getVersions(string $language): array
    {
        return SdkVersion::byLanguage($language)
            ->orderByDesc('released_at')
            ->get()
            ->toArray();
    }

    /**
     * 获取指定语言的兼容版本列表
     * （仅包含可用的版本，排除已 sunset 的）
     */
    public function getCompatibleVersions(string $language): array
    {
        return Cache::remember(sprintf(self::CACHE_KEY_VERSIONS, $language), self::CACHE_TTL, function () use ($language) {
            return SdkVersion::byLanguage($language)
                ->where('stage', '!=', 'sunset')
                ->orderByDesc('released_at')
                ->get()
                ->toArray();
        });
    }

    /**
     * 检查 SDK 版本是否需要升级
     *
     * @param string $language SDK语言
     * @param string $version 当前SDK版本
     * @return array{needs_upgrade: bool, reason: string|null, upgrade_to: string|null, stage: string}
     */
    public function checkUpgrade(string $language, string $version): array
    {
        $sdkVersion = SdkVersion::byLanguage($language)
            ->where('version', $version)
            ->first();

        if (!$sdkVersion) {
            return [
                'needs_upgrade' => true,
                'reason' => '未知版本',
                'upgrade_to' => $this->getRecommendedVersion($language),
                'stage' => 'unknown',
            ];
        }

        $result = [
            'needs_upgrade' => false,
            'reason' => null,
            'upgrade_to' => null,
            'stage' => $sdkVersion->stage,
        ];

        // 已停服版本必须升级
        if ($sdkVersion->stage === 'sunset') {
            $result['needs_upgrade'] = true;
            $result['reason'] = '当前版本已停服，必须升级到最新版本';
            $result['upgrade_to'] = $this->getRecommendedVersion($language);
            return $result;
        }

        // 已废弃版本且在宽限期后
        if ($sdkVersion->stage === 'deprecated') {
            $graceDays = config('sdk-version.strategy.deprecation_grace_days', 90);
            if ($sdkVersion->sunset_at && $sdkVersion->sunset_at->isPast()) {
                $result['needs_upgrade'] = true;
                $result['reason'] = '当前版本已过宽限期，必须升级';
                $result['upgrade_to'] = $this->getRecommendedVersion($language);
            } elseif ($sdkVersion->sunset_at && $sdkVersion->sunset_at->diffInDays(now()) <= config('sdk-version.strategy.force_upgrade_warn_days', 30)) {
                $result['needs_upgrade'] = true;
                $result['reason'] = '当前版本即将停服，建议尽快升级';
                $result['upgrade_to'] = $this->getRecommendedVersion($language);
            }
        }

        // 检查是否版本过旧
        $latest = $this->getLatestVersion($language);
        if ($latest && $this->isMajorBehind($sdkVersion->version, $latest->version)) {
            $result['needs_upgrade'] = true;
            $result['reason'] = '当前版本主版本落后，建议升级';
            $result['upgrade_to'] = $latest->version;
        }

        return $result;
    }

    /**
     * 获取升级路径（从旧版本到目标版本的步骤）
     */
    public function getUpgradePath(string $language, string $fromVersion): array
    {
        $versions = SdkVersion::byLanguage($language)
            ->orderBy('released_at')
            ->get();

        $path = [];
        $found = false;

        foreach ($versions as $v) {
            if ($v->version === $fromVersion) {
                $found = true;
                continue;
            }
            if ($found) {
                $path[] = [
                    'version' => $v->version,
                    'stage' => $v->stage,
                    'changelog' => $v->changelog,
                    'upgrade_notes' => $v->upgrade_notes,
                    'released_at' => $v->released_at?->toDateString(),
                ];
            }
        }

        return $path;
    }

    /**
     * 获取版本迁移指南
     */
    public function getMigrationGuide(string $language, ?string $targetVersion = null): array
    {
        $target = $targetVersion
            ? SdkVersion::byLanguage($language)->where('version', $targetVersion)->first()
            : SdkVersion::byLanguage($language)->where('is_current', true)->first();

        if (!$target) {
            return ['guide' => '未找到目标版本信息', 'target_version' => $targetVersion ?? 'latest'];
        }

        return [
            'target_version' => $target->version,
            'target_stage' => $target->stage,
            'min_api_version' => $target->min_api_version,
            'upgrade_notes' => $target->upgrade_notes,
            'changelog' => $target->changelog,
            'compatible_sdk_versions' => $target->compatible_sdk_versions,
        ];
    }

    /**
     * 标记版本为废弃
     */
    public function markDeprecated(int $id, ?int $graceDays = null): SdkVersion
    {
        $version = SdkVersion::findOrFail($id);
        $graceDays = $graceDays ?? config('sdk-version.strategy.deprecation_grace_days', 90);

        $version->update([
            'stage' => 'deprecated',
            'deprecated_at' => now(),
            'sunset_at' => now()->addDays($graceDays),
            'is_current' => false,
        ]);

        $this->clearCache($version->language);
        return $version;
    }

    /**
     * 标记版本为停服
     */
    public function markSunset(int $id): SdkVersion
    {
        $version = SdkVersion::findOrFail($id);

        $version->update([
            'stage' => 'sunset',
            'sunset_at' => now(),
            'is_current' => false,
            'allow_production' => false,
        ]);

        $this->clearCache($version->language);
        return $version;
    }

    /**
     * 获取指定语言的推荐版本
     */
    public function getRecommendedVersion(string $language): ?string
    {
        $current = SdkVersion::byLanguage($language)
            ->where('is_current', true)
            ->where('stage', 'stable')
            ->first();

        if ($current) {
            return $current->version;
        }

        $latest = SdkVersion::byLanguage($language)
            ->where('stage', 'stable')
            ->orderByDesc('released_at')
            ->first();

        return $latest?->version;
    }

    /**
     * 获取某语言的最新版本
     */
    public function getLatestVersion(string $language): ?SdkVersion
    {
        return SdkVersion::byLanguage($language)
            ->where('stage', '!=', 'sunset')
            ->orderByDesc('released_at')
            ->first();
    }

    /**
     * 获取所有语言的版本概览
     */
    public function getDashboard(): array
    {
        $languages = SdkVersion::LANGUAGES;
        $result = [];

        foreach ($languages as $lang) {
            $all = SdkVersion::byLanguage($lang)->orderByDesc('released_at')->get();
            $current = $all->where('is_current', true)->first();
            $latest = $all->where('stage', '!=', 'sunset')->first();

            $result[$lang] = [
                'name' => config("sdk-manager.versions.{$lang}.name", $lang),
                'total_versions' => $all->count(),
                'current_version' => $current?->version,
                'latest_version' => $latest?->version,
                'stages' => [
                    'stable' => $all->where('stage', 'stable')->count(),
                    'deprecated' => $all->where('stage', 'deprecated')->count(),
                    'sunset' => $all->where('stage', 'sunset')->count(),
                    'preview' => $all->where('stage', 'preview')->count(),
                ],
                'needs_upgrade' => $all->where('stage', 'deprecated')->where('sunset_at', '<=', now())->count()
                    + $all->where('stage', 'sunset')->count(),
            ];
        }

        return $result;
    }

    /**
     * 批量处理过期版本（将超过宽限期的 deprecated 标记为 sunset）
     */
    public function processExpiredDeprecations(): int
    {
        $expired = SdkVersion::where('stage', 'deprecated')
            ->where('sunset_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $version) {
            $version->update([
                'stage' => 'sunset',
                'allow_production' => false,
            ]);
            $this->clearCache($version->language);
            $count++;
        }

        return $count;
    }

    /**
     * 判断两个版本之间是否存在主版本差异
     */
    private function isMajorBehind(string $current, string $latest): bool
    {
        $curMajor = (int) explode('.', $current)[0];
        $latMajor = (int) explode('.', $latest)[0];

        // 如果当前版本主版本低于最新版且差值 >= 最小主版本配置
        $minMajor = config('sdk-version.strategy.minimum_major_version', 1);
        return ($latMajor - $curMajor) >= $minMajor;
    }

    /**
     * 清除缓存
     */
    private function clearCache(string $language): void
    {
        Cache::forget(sprintf(self::CACHE_KEY_VERSIONS, $language));
    }
}
