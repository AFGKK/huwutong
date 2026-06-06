<?php

namespace App\Services;

use App\Models\MaintenanceConfig;
use Illuminate\Support\Facades\Cache;

/**
 * 系统维护模式服务
 *
 * 功能：
 * - 一键开启/关闭维护模式
 * - 自定义维护公告 + 预计恢复时间
 * - IP 白名单绕过 + 路径白名单
 * - 自动定时关闭（K8s 就绪探针联动）
 * - 系统级维护：写入 framework/down
 */
class MaintenanceModeService
{
    const CACHE_KEY = 'maintenance:config';
    const CACHE_TTL = 60; // 60 秒，保持新鲜

    /**
     * 获取当前维护模式配置
     */
    public function getConfig(): ?MaintenanceConfig
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return MaintenanceConfig::where('is_enabled', true)->first();
        });
    }

    /**
     * 判断是否处于维护模式
     */
    public function isActive(): bool
    {
        $config = $this->getConfig();

        if (! $config) {
            return false;
        }

        // 检查自动关闭时间
        if ($config->auto_disable_at && now()->gte($config->auto_disable_at)) {
            $this->disable($config);
            return false;
        }

        return true;
    }

    /**
     * 检查请求是否可以绕过维护模式
     */
    public function canBypass(string $ip, string $path): bool
    {
        $config = $this->getConfig();

        if (! $config) {
            return false;
        }

        return $config->isIpWhitelisted($ip) || $config->isPathWhitelisted($path);
    }

    /**
     * 获取维护页面数据
     */
    public function getMaintenanceData(): array
    {
        $config = $this->getConfig();

        return [
            'title' => $config?->title ?? '系统维护中',
            'message' => $config?->message ?? '系统正在进行计划内维护，请稍后再试。',
            'scheduled_end_at' => $config?->scheduled_end_at?->toIso8601String(),
            'retry_after' => $config?->retry_after ?? 60,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * 启用维护模式
     */
    public function enable(array $data): MaintenanceConfig
    {
        // 禁用旧的活跃配置
        MaintenanceConfig::where('is_enabled', true)->update(['is_enabled' => false]);

        $config = MaintenanceConfig::create(array_merge($data, [
            'is_enabled' => true,
        ]));

        $this->clearCache();

        // 触发 Laravel 内置维护模式（可选）
        if ($data['system_maintenance'] ?? false) {
            \Artisan::call('down', [
                '--secret' => $data['secret'] ?? null,
                '--retry' => $data['retry_after'] ?? 60,
            ]);
        }

        return $config;
    }

    /**
     * 禁用维护模式
     */
    public function disable(?MaintenanceConfig $config = null): void
    {
        if ($config) {
            $config->update(['is_enabled' => false]);
        } else {
            MaintenanceConfig::where('is_enabled', true)->update(['is_enabled' => false]);
        }

        $this->clearCache();

        // 恢复 Laravel 内置维护模式
        if (app()->isDownForMaintenance()) {
            \Artisan::call('up');
        }
    }

    /**
     * 清除缓存
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
