<?php

namespace App\Services;

use App\Models\Device;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\TamperEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * License 健康评分服务 (M2-110)
 *
 * 为每个 License 计算综合健康分（满分100），
 * 考虑过期时间、设备占比、异常事件、安全评分等因素。
 */
class LicenseHealthScoreService
{
    const SCORE_EXPIRY_WEIGHT = 35;      // 到期时间权重
    const SCORE_DEVICE_WEIGHT = 25;      // 设备占比权重
    const SCORE_SECURITY_WEIGHT = 25;    // 安全评分权重
    const SCORE_ACTIVITY_WEIGHT = 15;    // 活跃度权重

    /**
     * 获取客户的所有 License 健康评分
     */
    public function getAllForCustomer(int $tenantId, int $customerId): array
    {
        $licenses = License::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->withCount(['devices', 'activations'])
            ->with(['product:id,name'])
            ->get();

        $results = [];
        foreach ($licenses as $license) {
            $results[] = $this->calculate($license, $tenantId);
        }

        // 按评分升序排列（不健康的排前面）
        usort($results, fn($a, $b) => $a['score'] <=> $b['score']);

        return $results;
    }

    /**
     * 获取单个 License 健康评分
     */
    public function getForLicense(int $licenseId, int $tenantId): ?array
    {
        $license = License::where('id', $licenseId)
            ->where('tenant_id', $tenantId)
            ->withCount(['devices', 'activations'])
            ->with(['product:id,name'])
            ->first();

        if (!$license) {
            return null;
        }

        return $this->calculate($license, $tenantId);
    }

    /**
     * 获取仪表盘总览数据
     */
    public function getDashboard(int $tenantId, int $customerId): array
    {
        $licenses = License::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->get();

        $total = $licenses->count();
        $scores = [];
        $healthyCount = 0;
        $warningCount = 0;
        $criticalCount = 0;

        foreach ($licenses as $license) {
            $result = $this->calculateBasic($license, $tenantId);
            $score = $result['score'];
            $scores[] = $score;

            if ($score >= 80) {
                $healthyCount++;
            } elseif ($score >= 60) {
                $warningCount++;
            } else {
                $criticalCount++;
            }
        }

        $avgScore = $total > 0 ? round(array_sum($scores) / $total, 1) : 0;

        // 关键改进建议（聚合所有 License）
        $topSuggestions = $this->getAggregatedSuggestions($licenses, $tenantId);

        return [
            'total_licenses' => $total,
            'average_score' => $avgScore,
            'healthy_count' => $healthyCount,
            'warning_count' => $warningCount,
            'critical_count' => $criticalCount,
            'top_suggestions' => $topSuggestions,
        ];
    }

    // ─── 私有计算逻辑 ───

    private function calculate(License $license, int $tenantId): array
    {
        $expiryScore = $this->calculateExpiryScore($license);
        $deviceScore = $this->calculateDeviceScore($license);
        $securityScore = $this->calculateSecurityScore($license, $tenantId);
        $activityScore = $this->calculateActivityScore($license);

        $totalScore = (int) round(
            $expiryScore * (self::SCORE_EXPIRY_WEIGHT / 100) +
            $deviceScore * (self::SCORE_DEVICE_WEIGHT / 100) +
            $securityScore * (self::SCORE_SECURITY_WEIGHT / 100) +
            $activityScore * (self::SCORE_ACTIVITY_WEIGHT / 100)
        );

        $totalScore = max(0, min(100, $totalScore));

        return [
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'product_name' => $license->product?->name,
            'status' => $license->status,
            'score' => $totalScore,
            'level' => $this->getLevel($totalScore),
            'expires_at' => $license->expires_at,
            'device_count' => $license->devices_count ?? $license->devices()->count(),
            'max_devices' => $license->max_devices,
            'details' => [
                'expiry_score' => (int) $expiryScore,
                'device_score' => (int) $deviceScore,
                'security_score' => (int) $securityScore,
                'activity_score' => (int) $activityScore,
            ],
            'suggestions' => $this->generateSuggestions($license, $expiryScore, $deviceScore, $securityScore, $activityScore),
        ];
    }

    /**
     * 轻量计算（不含详细建议，用于仪表盘聚合）
     */
    private function calculateBasic(License $license, int $tenantId): array
    {
        $expiryScore = $this->calculateExpiryScore($license);
        $deviceScore = $this->calculateDeviceScore($license);
        $securityScore = $this->calculateSecurityScore($license, $tenantId);
        $activityScore = $this->calculateActivityScore($license);

        $totalScore = (int) round(
            $expiryScore * (self::SCORE_EXPIRY_WEIGHT / 100) +
            $deviceScore * (self::SCORE_DEVICE_WEIGHT / 100) +
            $securityScore * (self::SCORE_SECURITY_WEIGHT / 100) +
            $activityScore * (self::SCORE_ACTIVITY_WEIGHT / 100)
        );

        $totalScore = max(0, min(100, $totalScore));

        return [
            'license_id' => $license->id,
            'score' => $totalScore,
            'level' => $this->getLevel($totalScore),
        ];
    }

    /**
     * 到期时间评分 (0-100)
     */
    private function calculateExpiryScore(License $license): float
    {
        if (in_array($license->status, ['expired', 'revoked', 'blacklisted'])) {
            return 0;
        }

        if (!$license->expires_at) {
            return 100; // 永久有效
        }

        $expiresAt = Carbon::parse($license->expires_at);
        $now = Carbon::now();
        $daysUntilExpiry = $now->diffInDays($expiresAt, false);

        // 已过期
        if ($daysUntilExpiry <= 0) {
            return 10;
        }

        // 30天内到期 → 线性下降
        if ($daysUntilExpiry <= 30) {
            return 10 + ($daysUntilExpiry / 30) * 60; // 10 ~ 70
        }

        // 90天内到期 → 70~90
        if ($daysUntilExpiry <= 90) {
            return 70 + (($daysUntilExpiry - 30) / 60) * 20; // 70 ~ 90
        }

        // 90天以上 → 满分
        return 100;
    }

    /**
     * 设备占比评分 (0-100)
     */
    private function calculateDeviceScore(License $license): float
    {
        if ($license->max_devices <= 0) {
            return 100; // 无限制
        }

        $deviceCount = $license->devices_count ?? $license->devices()->count();
        $percent = ($deviceCount / $license->max_devices) * 100;

        // 0% ~ 60% 设备使用 → 满分
        if ($percent <= 60) {
            return 100;
        }

        // 60% ~ 90% → 线性降到 60
        if ($percent <= 90) {
            return 100 - (($percent - 60) / 30) * 40; // 100 ~ 60
        }

        // 90% ~ 100% → 60 ~ 20
        if ($percent <= 100) {
            return 60 - (($percent - 90) / 10) * 40; // 60 ~ 20
        }

        // 超限 → 0~20
        return max(0, 20 - ($percent - 100) * 2);
    }

    /**
     * 安全评分 (0-100)
     */
    private function calculateSecurityScore(License $license, int $tenantId): float
    {
        $score = 100;

        // 异常事件扣分（篡改/安全事件）
        $tamperCount = TamperEvent::where('license_id', $license->id)->count();
        $score -= $tamperCount * 15;

        // 异常激活（短时间多地激活）
        $anomalousActivations = LicenseActivation::where('license_id', $license->id)
            ->where('is_anomaly', true)
            ->count();
        $score -= $anomalousActivations * 10;

        // 黑名单设备关联
        $blacklistedDevices = Device::where('license_id', $license->id)
            ->where('is_blacklisted', true)
            ->count();
        $score -= $blacklistedDevices * 20;

        return max(0, $score);
    }

    /**
     * 活跃度评分 (0-100)
     */
    private function calculateActivityScore(License $license): float
    {
        if (in_array($license->status, ['inactive', 'expired', 'revoked'])) {
            return 0;
        }

        // 最近7天是否有激活记录
        $recentActivations = LicenseActivation::where('license_id', $license->id)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        if ($recentActivations > 0) {
            return 100;
        }

        // 最近30天
        $monthActivations = LicenseActivation::where('license_id', $license->id)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        if ($monthActivations > 0) {
            return 70;
        }

        // 最近90天
        $quarterActivations = LicenseActivation::where('license_id', $license->id)
            ->where('created_at', '>=', Carbon::now()->subDays(90))
            ->count();

        if ($quarterActivations > 0) {
            return 40;
        }

        return 10;
    }

    /**
     * 生成改进建议
     */
    private function generateSuggestions(License $license, float $expiryScore, float $deviceScore, float $securityScore, float $activityScore): array
    {
        $suggestions = [];

        // 到期相关
        if ($expiryScore < 30 && $license->expires_at) {
            $daysLeft = Carbon::now()->diffInDays(Carbon::parse($license->expires_at), false);
            if ($daysLeft <= 0) {
                $suggestions[] = [
                    'type' => 'critical',
                    'category' => 'expiry',
                    'message' => 'License 已过期，请立即续期以恢复服务。',
                    'action' => 'renew',
                ];
            } else {
                $suggestions[] = [
                    'type' => 'warning',
                    'category' => 'expiry',
                    'message' => "License 将在 {$daysLeft} 天后到期，建议提前续期避免服务中断。",
                    'action' => 'renew',
                ];
            }
        } elseif ($expiryScore < 70 && $license->expires_at) {
            $daysLeft = Carbon::now()->diffInDays(Carbon::parse($license->expires_at), false);
            $suggestions[] = [
                'type' => 'info',
                'category' => 'expiry',
                'message' => "License 将在 {$daysLeft} 天后到期，届时请注意续期。",
                'action' => 'renew',
            ];
        }

        // 设备相关
        if ($deviceScore < 30) {
            $deviceCount = $license->devices_count ?? $license->devices()->count();
            $suggestions[] = [
                'type' => 'critical',
                'category' => 'device',
                'message' => "设备数量 ({$deviceCount}) 已超限或接近上限 ({$license->max_devices})，请解绑不活跃设备或升级 License。",
                'action' => 'manage_devices',
            ];
        } elseif ($deviceScore < 70) {
            $deviceCount = $license->devices_count ?? $license->devices()->count();
            $suggestions[] = [
                'type' => 'warning',
                'category' => 'device',
                'message' => "设备使用率较高 ({$deviceCount}/{$license->max_devices})，建议留意设备数量。",
                'action' => 'manage_devices',
            ];
        }

        // 安全相关
        if ($securityScore < 50) {
            $suggestions[] = [
                'type' => 'critical',
                'category' => 'security',
                'message' => '检测到安全事件（篡改/异常激活），请立即审查并加强安全措施。',
                'action' => 'review_security',
            ];
        } elseif ($securityScore < 80) {
            $suggestions[] = [
                'type' => 'warning',
                'category' => 'security',
                'message' => '存在少量安全警告，建议定期审查激活记录。',
                'action' => 'review_security',
            ];
        }

        // 活跃度
        if ($activityScore < 30) {
            $suggestions[] = [
                'type' => 'warning',
                'category' => 'activity',
                'message' => 'License 长期未使用，若不再需要可考虑释放资源。',
                'action' => 'review_usage',
            ];
        }

        return $suggestions;
    }

    /**
     * 聚合所有 License 的改进建议（Dashboard用）
     */
    private function getAggregatedSuggestions(Collection $licenses, int $tenantId): array
    {
        $suggestions = [];
        $expiringSoon = 0;
        $deviceFull = 0;
        $securityIssues = 0;
        $inactive = 0;

        foreach ($licenses as $license) {
            if ($license->expires_at && Carbon::parse($license->expires_at)->diffInDays(Carbon::now(), false) <= 30) {
                $expiringSoon++;
            }

            $deviceCount = $license->devices()->count();
            if ($license->max_devices > 0 && ($deviceCount / $license->max_devices) >= 0.85) {
                $deviceFull++;
            }

            $tamperCount = TamperEvent::where('license_id', $license->id)->count();
            if ($tamperCount > 0) {
                $securityIssues++;
            }

            $recentActivity = LicenseActivation::where('license_id', $license->id)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count();
            if ($recentActivity === 0) {
                $inactive++;
            }
        }

        if ($expiringSoon > 0) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => "{$expiringSoon} 个 License 将在30天内到期，建议及时续期。",
            ];
        }
        if ($deviceFull > 0) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => "{$deviceFull} 个 License 设备使用率超过85%，建议升级或解绑。",
            ];
        }
        if ($securityIssues > 0) {
            $suggestions[] = [
                'type' => 'critical',
                'message' => "{$securityIssues} 个 License 存在安全事件，请立即审查。",
            ];
        }
        if ($inactive > 0) {
            $suggestions[] = [
                'type' => 'info',
                'message' => "{$inactive} 个 License 近30天无活跃记录，可考虑释放资源。",
            ];
        }

        return $suggestions;
    }

    /**
     * 根据分数确定等级
     */
    private function getLevel(int $score): string
    {
        if ($score >= 80) return 'healthy';
        if ($score >= 60) return 'warning';
        return 'critical';
    }
}
