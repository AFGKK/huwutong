<?php

namespace App\Services;

use App\Models\AnnounceBanner;
use Illuminate\Support\Facades\Cache;

/**
 * 系统公告横幅服务
 *
 * 按时间窗口、角色可见性获取当前应展示的公告。
 */
class AnnounceBannerService
{
    const CACHE_KEY = 'announce_banners:active';
    const CACHE_TTL = 300; // 5 分钟

    /**
     * 获取当前活跃的公告列表
     */
    public function getActiveBanners(?string $role = null): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () use ($role) {
            $banners = AnnounceBanner::where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->orderBy('sort_order')
                ->orderBy('created_at', 'desc')
                ->get();

            return $banners
                ->filter(fn ($b) => $b->isVisibleToRole($role))
                ->values()
                ->toArray();
        }) ?: [];
    }

    /**
     * 创建公告
     */
    public function create(array $data): AnnounceBanner
    {
        $banner = AnnounceBanner::create($data);
        $this->clearCache();
        return $banner;
    }

    /**
     * 更新公告
     */
    public function update(AnnounceBanner $banner, array $data): AnnounceBanner
    {
        $banner->update($data);
        $this->clearCache();
        return $banner->fresh();
    }

    /**
     * 删除公告
     */
    public function delete(AnnounceBanner $banner): void
    {
        $banner->delete();
        $this->clearCache();
    }

    /**
     * 清除缓存
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
