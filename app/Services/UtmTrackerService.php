<?php

namespace App\Services;

use App\Models\User;
use App\Models\UtmTrackingRecord;
use Illuminate\Support\Facades\Log;

/**
 * UTM/渠道归因追踪服务 (M2-104)
 */
class UtmTrackerService
{
    /**
     * 记录 UTM 访问（首次访问 / 转化时调用）
     */
    public function record(array $data, ?string $sessionId = null): UtmTrackingRecord
    {
        $data['session_id'] = $sessionId ?? $this->generateSessionId();
        $data['channel_group'] = $data['channel_group'] ?? $this->resolveChannelGroup($data);

        return UtmTrackingRecord::create($data);
    }

    /**
     * 注册时关联 UTM 到用户
     */
    public function associateUser(int $userId, string $sessionId): ?UtmTrackingRecord
    {
        $record = UtmTrackingRecord::where('session_id', $sessionId)
            ->whereNull('trackable_id')
            ->latest()
            ->first();

        if ($record) {
            $record->update([
                'trackable_type' => 'user',
                'trackable_id' => $userId,
                'attribution_type' => 'conversion',
            ]);

            // 更新用户首访UTM
            User::where('id', $userId)->update([
                'first_utm_source' => $record->utm_source,
                'first_utm_medium' => $record->utm_medium,
                'first_utm_campaign' => $record->utm_campaign,
                'first_utm_landed_at' => $record->created_at,
            ]);
        }

        return $record;
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(string $startDate, string $endDate): array
    {
        $records = UtmTrackingRecord::whereBetween('created_at', [$startDate, $endDate])
            ->where('attribution_type', 'first_visit')
            ->get();

        $conversions = UtmTrackingRecord::whereBetween('created_at', [$startDate, $endDate])
            ->where('attribution_type', 'conversion')
            ->where('trackable_type', 'user')
            ->get();

        // 按渠道分组统计
        $channelStats = $records->groupBy('channel_group')->map(function ($group) use ($conversions) {
            $groupName = $group->first()->channel_group ?? '未知';
            $visits = $group->count();
            $conversionCount = $conversions->where('channel_group', $groupName)->count();
            return [
                'channel' => $groupName,
                'visits' => $visits,
                'conversions' => $conversionCount,
                'conversion_rate' => $visits > 0 ? round($conversionCount / $visits * 100, 2) : 0,
            ];
        })->values();

        // 按来源统计
        $sourceStats = $records->groupBy('utm_source')->map(function ($group) {
            return [
                'source' => $group->first()->utm_source ?? '(direct)',
                'visits' => $group->count(),
                'mediums' => $group->groupBy('utm_medium')->map->count(),
            ];
        })->values();

        return [
            'total_visits' => $records->count(),
            'total_conversions' => $conversions->count(),
            'overall_rate' => $records->count() > 0
                ? round($conversions->count() / $records->count() * 100, 2) : 0,
            'by_channel' => $channelStats,
            'by_source' => $sourceStats,
            'channel_groups' => config('utm-tracker.channel_groups', []),
            'attribution_models' => config('utm-tracker.attribution_models', []),
        ];
    }

    /**
     * 获取渠道归因报告
     */
    public function getAttributionReport(string $startDate, string $endDate, string $model = 'first_touch'): array
    {
        $conversions = UtmTrackingRecord::whereBetween('created_at', [$startDate, $endDate])
            ->where('attribution_type', 'conversion')
            ->whereNotNull('trackable_id')
            ->get();

        $totalConversions = $conversions->count();

        // 按渠道分组计算价值
        $channelValue = $conversions->groupBy('channel_group')->map(function ($group) use ($totalConversions) {
            $groupName = $group->first()->channel_group ?? '未知';
            $count = $group->count();
            return [
                'channel' => $groupName,
                'conversions' => $count,
                'percentage' => $totalConversions > 0 ? round($count / $totalConversions * 100, 2) : 0,
            ];
        })->values()->sortByDesc('conversions')->values()->toArray();

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'model' => $model,
            'model_label' => config("utm-tracker.attribution_models.{$model}", $model),
            'total_conversions' => $totalConversions,
            'channels' => $channelValue,
        ];
    }

    /**
     * 获取按来源/媒介的详细统计
     */
    public function getSourceDetail(string $startDate, string $endDate, ?string $channelGroup = null): array
    {
        $query = UtmTrackingRecord::whereBetween('created_at', [$startDate, $endDate]);

        if ($channelGroup) {
            $query->where('channel_group', $channelGroup);
        }

        $records = $query->get();

        return [
            'sources' => $records->groupBy('utm_source')->map(function ($group) {
                $source = $group->first()->utm_source ?? '(direct)';
                $visits = $group->where('attribution_type', 'first_visit')->count();
                $conversions = $group->where('attribution_type', 'conversion')->count();
                return [
                    'source' => $source,
                    'visits' => $visits,
                    'conversions' => $conversions,
                    'rate' => $visits > 0 ? round($conversions / $visits * 100, 2) : 0,
                    'mediums' => $group->groupBy('utm_medium')->map(function ($mGroup) {
                        return [
                            'visits' => $mGroup->where('attribution_type', 'first_visit')->count(),
                            'conversions' => $mGroup->where('attribution_type', 'conversion')->count(),
                        ];
                    }),
                ];
            })->values(),
        ];
    }

    /**
     * 获取用户 UTM 历史
     */
    public function getUserUtmHistory(int $userId): array
    {
        return UtmTrackingRecord::where('trackable_type', 'user')
            ->where('trackable_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 解析渠道分组
     */
    public function resolveChannelGroup(array $data): string
    {
        $source = strtolower($data['utm_source'] ?? '');
        $medium = strtolower($data['utm_medium'] ?? '');

        foreach (config('utm-tracker.channel_groups', []) as $group => $rules) {
            foreach ($rules as $field => $values) {
                $value = $field === 'source' ? $source : $medium;
                if (in_array($value, $values)) {
                    return $group;
                }
            }
        }

        if ($source || $medium) {
            return 'Other';
        }

        return 'Direct';
    }

    /**
     * 生成会话 ID
     */
    public function generateSessionId(): string
    {
        return md5(uniqid('utm_', true) . microtime(true));
    }

    /**
     * 获取渠道选项
     */
    public function getChannelGroups(): array
    {
        return array_keys(config('utm-tracker.channel_groups', []));
    }
}
