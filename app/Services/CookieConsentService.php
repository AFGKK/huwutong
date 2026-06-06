<?php

namespace App\Services;

use App\Models\CookieConsentConfig;
use App\Models\CookieConsentLog;
use Illuminate\Support\Facades\Cache;

class CookieConsentService
{
    const CACHE_KEY = 'cookie_consent:config';
    const CACHE_TTL = 3600;

    /**
     * 获取当前配置（带默认值）
     */
    public function getConfig(): CookieConsentConfig
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $config = CookieConsentConfig::first();

            if (!$config) {
                $config = CookieConsentConfig::create([
                    'categories' => CookieConsentConfig::defaultCategories(),
                ]);
            }

            return $config;
        });
    }

    /**
     * 更新配置
     */
    public function updateConfig(array $data): CookieConsentConfig
    {
        $config = CookieConsentConfig::first();
        if (!$config) {
            $config = new CookieConsentConfig();
        }

        $config->fill($data);
        $config->save();

        Cache::forget(self::CACHE_KEY);

        return $config->fresh();
    }

    /**
     * 记录用户同意行为
     */
    public function recordConsent(
        ?int $userId,
        string $ip,
        string $action,
        ?array $selectedCategories,
        ?string $userAgent
    ): CookieConsentLog {
        return CookieConsentLog::create([
            'user_id' => $userId,
            'ip' => $ip,
            'action' => $action,
            'selected_categories' => $selectedCategories,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }

    /**
     * 获取同意日志（管理用）
     */
    public function getLogs(int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return CookieConsentLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * 获取概览统计
     */
    public function getStats(): array
    {
        $total = CookieConsentLog::count();
        $accepted = CookieConsentLog::where('action', 'accepted')->count();
        $rejected = CookieConsentLog::where('action', 'rejected')->count();
        $customized = CookieConsentLog::where('action', 'customized')->count();

        $today = CookieConsentLog::whereDate('created_at', today())->count();

        // 分类统计
        $categoryCounts = [];
        $logs = CookieConsentLog::whereNotNull('selected_categories')->get();
        foreach ($logs as $log) {
            $cats = $log->selected_categories ?? [];
            foreach ($cats as $cat) {
                $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
            }
        }

        return [
            'total' => $total,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'customized' => $customized,
            'today' => $today,
            'category_breakdown' => $categoryCounts,
        ];
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
