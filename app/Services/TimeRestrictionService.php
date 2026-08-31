<?php

namespace App\Services;

use App\Models\License;
use App\Models\TimeRestrictionConfig;
use App\Models\TimeRestrictionLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * License 使用时段限制服务 (M3-77)
 *
 * 支持：
 * - 每周可用时段（如仅工作日 9:00-18:00）
 * - 特定期日特殊时段（优先级高于 weekly_schedule）
 * - 节假日日历（节假日默认不可用）
 * - 时区感知（基于配置的时区计算本地时间）
 * - 宽限机制（超出时段后允许宽限 N 分钟）
 * - IP 白名单例外
 */
class TimeRestrictionService
{
    /**
     * 检查 License 在当前时刻是否允许使用
     *
     * @return array ['allowed' => bool, 'reason' => string, 'action' => string]
     */
    public function check(License $license, ?string $clientIp = null): array
    {
        // 查找该 License 的时段限制配置（也检查产品级配置）
        $config = $this->resolveConfig($license);

        if (! $config || ! $config->is_active) {
            return ['allowed' => true, 'reason' => __('app.time_restriction.time_restriction_feb96556ec'), 'action' => 'allowed'];
        }

        // IP 白名单例外检查
        if ($clientIp && $this->isIpAllowed($config, $clientIp)) {
            $this->logCheck($config, $license, 'allowed', __('app.time_restriction.time_restriction_c5b2bd4868'));
            return ['allowed' => true, 'reason' => __('app.time_restriction.time_restriction_c5b2bd4868'), 'action' => 'allowed'];
        }

        $timezone = $config->timezone ?: 'UTC';
        $now = Carbon::now($timezone);

        // 1. 检查特定期日（special_schedule 优先于 weekly_schedule）
        $specialResult = $this->checkSpecialSchedule($config, $now);
        if ($specialResult) {
            return $specialResult;
        }

        // 2. 检查是否为节假日
        $todayDate = $now->format('Y-m-d');
        $holidays = $config->holidays ?? [];

        if (in_array($todayDate, $holidays)) {
            // 节假日默认不可用
            $this->logCheck($config, $license, 'denied', __('app.time_restriction.time_restriction_a681b4d5f8'));
            return [
                'allowed' => false,
                'reason' => __('app.time_restriction.time_restriction_235eea21a1'),
                'action' => $config->out_of_hours_action,
            ];
        }

        // 3. 检查每周可用时段
        $dayOfWeek = (int) $now->format('w'); // 0=周日, 1=周一 ... 6=周六
        $currentTime = $now->format('H:i');
        $weeklySchedule = $config->weekly_schedule ?? [];

        $daySchedule = null;
        foreach ($weeklySchedule as $entry) {
            if ((int) ($entry['day'] ?? -1) === $dayOfWeek) {
                $daySchedule = $entry;
                break;
            }
        }

        if (! $daySchedule) {
            // 无该日排期 — 不可用
            $dayNames = [__('app.time_restriction.time_restriction_562d7476ab'), __('app.time_restriction.time_restriction_1603b069c2'), __('app.time_restriction.time_restriction_b5a6a07e48'), __('app.time_restriction.time_restriction_e60725e762'), __('app.time_restriction.time_restriction_170fc8e27c'), __('app.time_restriction.time_restriction_eb79cea638'), __('app.time_restriction.time_restriction_2457513054')];
            $this->logCheck($config, $license, 'denied', "{$dayNames[$dayOfWeek]}无可用时段");
            return [
                'allowed' => false,
                'reason' => "{$dayNames[$dayOfWeek]}无可用时段",
                'action' => $config->out_of_hours_action,
            ];
        }

        $startTime = $daySchedule['start'] ?? '00:00';
        $endTime = $daySchedule['end'] ?? '23:59';

        // 检查是否在可用时段内
        if ($currentTime >= $startTime && $currentTime <= $endTime) {
            $this->logCheck($config, $license, 'allowed', __('app.time_restriction.time_restriction_38bf847b4e'));
            return ['allowed' => true, 'reason' => __('app.time_restriction.time_restriction_38bf847b4e'), 'action' => 'allowed'];
        }

        // 4. 宽限机制
        if ($config->out_of_hours_action === 'grace' && $config->grace_minutes > 0) {
            $graceEndAt = Carbon::parse($endTime, $timezone)->addMinutes((int) $config->grace_minutes);
            if ($now <= $graceEndAt) {
                $this->logCheck($config, $license, 'grace', __('app.time_restriction.time_restriction_cb20591947'));
                return [
                    'allowed' => true,
                    'reason' => __('app.time_restriction.time_restriction_c99f07db0e'),
                    'action' => 'grace',
                    'grace_until' => $graceEndAt->toIso8601String(),
                ];
            }
        }

        // 非可用时段
        $this->logCheck($config, $license, 'denied', "可用时段 {$startTime}-{$endTime}");
        return [
            'allowed' => false,
            'reason' => "当前不在可用时段内（可用时段: {$startTime}-{$endTime}）",
            'action' => $config->out_of_hours_action,
        ];
    }

    /**
     * 检查特定期日特殊时段
     */
    protected function checkSpecialSchedule(TimeRestrictionConfig $config, Carbon $now): ?array
    {
        $todayDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');
        $specialSchedule = $config->special_schedule ?? [];

        foreach ($specialSchedule as $entry) {
            if (($entry['date'] ?? '') !== $todayDate) {
                continue;
            }

            $startTime = $entry['start'] ?? '00:00';
            $endTime = $entry['end'] ?? '23:59';

            if ($currentTime >= $startTime && $currentTime <= $endTime) {
                return ['allowed' => true, 'reason' => __('app.time_restriction.time_restriction_7a76e129a5'), 'action' => 'allowed'];
            }

            // 特定期日排期匹配但不在此时间段内
            return [
                'allowed' => false,
                'reason' => "今日特殊时段为 {$startTime}-{$endTime}",
                'action' => $config->out_of_hours_action,
            ];
        }

        return null; // 无匹配特定期日
    }

    /**
     * 检查 IP 是否在白名单中
     */
    protected function isIpAllowed(TimeRestrictionConfig $config, string $ip): bool
    {
        $allowedRanges = $config->allowed_ip_ranges;
        if (! $allowedRanges) {
            return false;
        }

        $ranges = explode(',', $allowedRanges);

        foreach ($ranges as $range) {
            $range = trim($range);
            if ($range === $ip) {
                return true;
            }

            // CIDR 检查
            if (str_contains($range, '/')) {
                if ($this->ipInCidr($ip, $range)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 检查 IP 是否在 CIDR 范围内
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);

        return ($ip & $mask) === ($subnet & $mask);
    }

    /**
     * 解析 License 对应的时段限制配置
     * 优先级：License 级 > Product 级
     */
    protected function resolveConfig(License $license): ?TimeRestrictionConfig
    {
        // 先查 License 级别配置
        $config = TimeRestrictionConfig::where('restrictable_type', License::class)
            ->where('restrictable_id', $license->id)
            ->where('is_active', true)
            ->first();

        if ($config) {
            return $config;
        }

        // 再查 Product 级别配置
        if ($license->product_id) {
            $config = TimeRestrictionConfig::where('restrictable_type', get_class($license->product))
                ->where('restrictable_id', $license->product_id)
                ->where('is_active', true)
                ->first();
        }

        return $config;
    }

    /**
     * 记录检查日志
     */
    protected function logCheck(TimeRestrictionConfig $config, License $license, string $result, string $reason): void
    {
        try {
            TimeRestrictionLog::create([
                'config_id' => $config->id,
                'license_id' => $license->id,
                'result' => $result,
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'timezone_used' => $config->timezone,
                'checked_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('时段限制日志写入失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取配置状态摘要（前端展示用）
     */
    public function getConfigSummary(?TimeRestrictionConfig $config): array
    {
        if (! $config || ! $config->is_active) {
            return ['enabled' => false, 'summary' => __('app.time_restriction.time_restriction_2d0e50c1ef')];
        }

        $weeklySummary = [];
        $dayNames = [__('app.time_restriction.time_restriction_562d7476ab'), __('app.time_restriction.time_restriction_1603b069c2'), __('app.time_restriction.time_restriction_b5a6a07e48'), __('app.time_restriction.time_restriction_e60725e762'), __('app.time_restriction.time_restriction_170fc8e27c'), __('app.time_restriction.time_restriction_eb79cea638'), __('app.time_restriction.time_restriction_2457513054')];

        foreach (($config->weekly_schedule ?? []) as $entry) {
            $day = (int) ($entry['day'] ?? 0);
            $weeklySummary[] = "{$dayNames[$day]}: {$entry['start']}-{$entry['end']}";
        }

        $holidayCount = count($config->holidays ?? []);

        return [
            'enabled' => true,
            'timezone' => $config->timezone,
            'weekly_schedule' => $weeklySummary,
            'special_dates' => count($config->special_schedule ?? []),
            'holiday_count' => $holidayCount,
            'out_of_hours_action' => $config->out_of_hours_action,
            'grace_minutes' => $config->grace_minutes,
            'has_ip_whitelist' => ! empty($config->allowed_ip_ranges),
        ];
    }
}
