<?php

namespace App\Services;

use App\Models\ApiDocEndpoint;
use App\Models\ApiKey;
use App\Models\SdkHeartbeat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 开发者门户服务 (M2-86)
 *
 * 聚合开发者生态各模块数据，提供一站式开发者门户。
 */
class DevPortalService
{
    /**
     * 门户看板数据
     */
    public function dashboard(int $userId): array
    {
        return [
            'sdks' => $this->getSdkList(),
            'quick_links' => $this->getQuickLinks(),
            'quickstart_steps' => $this->getQuickstartSteps(),
            'stats' => $this->getStats($userId),
            'recent_activity' => $this->getRecentActivity($userId),
        ];
    }

    /**
     * SDK 列表
     */
    public function getSdkList(): array
    {
        $sdks = config('dev-portal.sdks', []);
        $result = [];
        foreach ($sdks as $lang => $info) {
            $result[] = array_merge(['language' => $lang], $info);
        }
        return $result;
    }

    /**
     * 快速链接
     */
    public function getQuickLinks(): array
    {
        return config('dev-portal.quick_links', []);
    }

    /**
     * 快速开始步骤
     */
    public function getQuickstartSteps(): array
    {
        return config('dev-portal.quickstart_steps', []);
    }

    /**
     * 开发者统计
     */
    protected function getStats(int $userId): array
    {
        $user = User::find($userId);

        $apiKeyCount = 0;
        $playgroundCount = 0;
        $sdkVersions = [];

        // API Key 计数
        try {
            $apiKeyCount = ApiKey::where('user_id', $userId)->count();
        } catch (\Throwable $e) {
            $apiKeyCount = 0;
        }

        // Playground 调用次数（今日）
        try {
            $playgroundCount = (int) DB::table('api_playground_logs')
                ->where('user_id', $userId)
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
        } catch (\Throwable $e) {
            $playgroundCount = 0;
        }

        // SDK 版本分布
        try {
            $sdkVersions = SdkHeartbeat::selectRaw('sdk_version, COUNT(DISTINCT license_id) as count')
                ->groupBy('sdk_version')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            $sdkVersions = [];
        }

        return [
            'api_key_count' => $apiKeyCount,
            'playground_today' => $playgroundCount,
            'sdk_versions' => $sdkVersions,
            'api_endpoints_count' => ApiDocEndpoint::count(),
            'total_sdks' => count(config('dev-portal.sdks', [])),
        ];
    }

    /**
     * 最近活动
     */
    protected function getRecentActivity(int $userId): array
    {
        $activity = [];

        // 最近创建的 API Key
        try {
            $recentKeys = ApiKey::where('user_id', $userId)
                ->latest()->limit(5)->get(['name', 'created_at']);
            foreach ($recentKeys as $key) {
                $activity[] = [
                    'type' => 'api_key_created',
                    'label' => "创建了 API Key: {$key->name}",
                    'time' => $key->created_at->diffForHumans(),
                    'created_at' => $key->created_at->toDateTimeString(),
                ];
            }
        } catch (\Throwable $e) {}

        // 最近 Playground 调用
        try {
            $recentPlayground = DB::table('api_playground_logs')
                ->where('user_id', $userId)
                ->latest()->limit(5)->get(['endpoint', 'method', 'created_at']);
            foreach ($recentPlayground as $log) {
                $activity[] = [
                    'type' => 'playground',
                    'label' => "Playground: {$log->method} {$log->endpoint}",
                    'time' => \Carbon\Carbon::parse($log->created_at)->diffForHumans(),
                    'created_at' => $log->created_at,
                ];
            }
        } catch (\Throwable $e) {}

        // 按时间排序
        usort($activity, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return array_slice($activity, 0, 10);
    }

    /**
     * 公开门户数据（无需认证）
     */
    public function publicData(): array
    {
        return [
            'sdks' => $this->getSdkList(),
            'quickstart_steps' => $this->getQuickstartSteps(),
            'api_endpoints_count' => ApiDocEndpoint::count(),
        ];
    }
}
