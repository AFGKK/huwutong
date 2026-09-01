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
     * 维护模式下始终放行的路径（健康检查、关维护、登录），避免管理员把自己锁死。
     *
     * @return list<string>
     */
    public function defaultBypassPaths(): array
    {
        return [
            'up',
            'api/health',
            'api/health/*',
            'api/maintenance/*',
            'api/login',
            'api/phone/login',
            'api/oauth/login',
            'api/mfa/login',
            'api/auth/webauthn/login/*',
            'sanctum/csrf-cookie',
            'build',
            'build/login',
            'build/register',
            'auth',
            'auth/*',
        ];
    }

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

        if ($config) {
            // 检查自动关闭时间
            if ($config->auto_disable_at && now()->gte($config->auto_disable_at)) {
                $this->disable($config);

                return $this->isSiteSettingMaintenanceEnabled();
            }

            return true;
        }

        // 兼容后台「系统设置」里的 maintenance_enabled 开关
        return $this->isSiteSettingMaintenanceEnabled();
    }

    /**
     * SiteSetting 简易维护开关（与 MaintenanceConfig 并存时的回退源）
     */
    public function isSiteSettingMaintenanceEnabled(): bool
    {
        try {
            if (! function_exists('site_setting')) {
                return false;
            }

            return (string) site_setting('maintenance_enabled', '0') === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 检查请求是否可以绕过维护模式
     */
    public function canBypass(string $ip, string $path): bool
    {
        $path = ltrim($path, '/');

        if ($this->matchesPathPatterns($path, $this->defaultBypassPaths())) {
            return true;
        }

        $config = $this->getConfig();

        if ($config) {
            return $config->isIpWhitelisted($ip) || $config->isPathWhitelisted($path);
        }

        if (! $this->isSiteSettingMaintenanceEnabled()) {
            return false;
        }

        $allowed = $this->safeSiteSetting('maintenance_allowed_ips', '');
        $ips = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $allowed) ?: []));

        return $ips !== [] && in_array($ip, $ips, true);
    }

    protected function safeSiteSetting(string $key, string $default = ''): string
    {
        try {
            if (! function_exists('site_setting')) {
                return $default;
            }

            return (string) site_setting($key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * @param  list<string>  $patterns
     */
    public function matchesPathPatterns(string $path, array $patterns): bool
    {
        $path = ltrim($path, '/');

        foreach ($patterns as $pattern) {
            $pattern = ltrim((string) $pattern, '/');
            $regex = str_replace(['*', '/'], ['.*', '\/'], $pattern);
            if ($regex !== '' && preg_match('/^'.$regex.'$/', $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取维护页面数据
     */
    public function getMaintenanceData(): array
    {
        $config = $this->getConfig();

        if ($config) {
            return [
                'title' => $config->title ?? '系统维护中',
                'message' => $config->message ?? '系统正在进行计划内维护，请稍后再试。',
                'scheduled_end_at' => $config->scheduled_end_at?->toIso8601String(),
                'retry_after' => $config->retry_after ?? 60,
                'timestamp' => now()->toIso8601String(),
                'source' => 'maintenance_config',
            ];
        }

        return [
            'title' => '系统维护中',
            'message' => $this->safeSiteSetting('maintenance_message', '系统维护中，请稍后再试。'),
            'scheduled_end_at' => null,
            'retry_after' => 60,
            'timestamp' => now()->toIso8601String(),
            'source' => 'site_setting',
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
        $this->syncSiteSettingFlag(true, $config->message ?? null);

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
        $this->syncSiteSettingFlag(false);

        // 恢复 Laravel 内置维护模式
        if (app()->isDownForMaintenance()) {
            \Artisan::call('up');
        }
    }

    /**
     * 将维护开关镜像到 SiteSetting，避免后台两处状态不一致
     */
    public function syncSiteSettingFlag(bool $enabled, ?string $message = null): void
    {
        try {
            if (! class_exists(\App\Models\SiteSetting::class)) {
                return;
            }

            \App\Models\SiteSetting::updateOrCreate(
                ['key' => 'maintenance_enabled'],
                [
                    'group' => 'maintenance',
                    'value' => $enabled ? '1' : '0',
                    'type' => 'switch',
                    'is_public' => true,
                    'description' => '启用维护模式',
                ]
            );

            if ($message !== null && $message !== '') {
                \App\Models\SiteSetting::updateOrCreate(
                    ['key' => 'maintenance_message'],
                    [
                        'group' => 'maintenance',
                        'value' => $message,
                        'type' => 'textarea',
                        'is_public' => true,
                        'description' => '维护提示信息',
                    ]
                );
            }

            \Illuminate\Support\Facades\Cache::forget('site_settings_all');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::debug('syncSiteSettingFlag failed: '.$e->getMessage());
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
