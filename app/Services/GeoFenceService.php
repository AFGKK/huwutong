<?php

namespace App\Services;

use App\Models\LicenseRestriction;
use App\Models\LicenseRestrictionLog;
use Illuminate\Support\Facades\Log;

/**
 * 地理围栏服务 (M2-93)
 *
 * 按国家/地区限制 License 激活
 */
class GeoFenceService
{
    /**
     * 验证地理位置是否允许
     */
    public function check(int $licenseId, string $ipAddress, string $context = 'activate'): array
    {
        $restriction = LicenseRestriction::active()
            ->byType('geo_fence')
            ->forLicense($licenseId)
            ->first();

        if (!$restriction) {
            return ['allowed' => true, 'reason' => null];
        }

        $country = $this->lookupCountry($ipAddress);

        if (!$country) {
            $action = $restriction->unknown_location_action ?? 'allow';
            if ($action === 'block') {
                return $this->logAndReturn($licenseId, $ipAddress, null, 'blocked', '无法识别地理位置', $restriction);
            }
            if ($action === 'audit') {
                return $this->logAndReturn($licenseId, $ipAddress, null, 'audited', '无法识别地理位置(仅审计)', $restriction);
            }
            return $this->logAndReturn($licenseId, $ipAddress, null, 'allowed', '无法识别地理位置(允许)', $restriction);
        }

        // 检查黑名单国家
        $blocked = $restriction->blocked_countries ?? [];
        if (in_array($country, $blocked)) {
            return $this->logAndReturn($licenseId, $ipAddress, $country, 'blocked', "国家 {$country} 在黑名单中", $restriction);
        }

        // 检查白名单国家
        $allowed = $restriction->allowed_countries ?? [];
        if (!empty($allowed)) {
            if (in_array($country, $allowed)) {
                return $this->logAndReturn($licenseId, $ipAddress, $country, 'allowed', "国家 {$country} 在白名单中", $restriction);
            }
            if ($restriction->action === 'block') {
                return $this->logAndReturn($licenseId, $ipAddress, $country, 'blocked', "国家 {$country} 不在白名单中", $restriction);
            }
            if ($restriction->action === 'audit') {
                return $this->logAndReturn($licenseId, $ipAddress, $country, 'audited', "国家 {$country} 不在白名单中(仅审计)", $restriction);
            }
        }

        return $this->logAndReturn($licenseId, $ipAddress, $country, 'allowed', null, $restriction);
    }

    /**
     * 查询 IP 归属国家
     */
    protected function lookupCountry(string $ip): ?string
    {
        // 优先使用 MaxMind GeoIP
        $dbPath = config('license-restrictions.geo_fence.maxmind_db_path');
        if (file_exists($dbPath)) {
            try {
                $reader = new \MaxMind\Db\Reader($dbPath);
                $data = $reader->get($ip);
                $reader->close();
                if ($data && isset($data['country']['iso_code'])) {
                    return $data['country']['iso_code'];
                }
            } catch (\Throwable $e) {
                Log::warning('MaxMind GeoIP 查询失败', ['ip' => $ip, 'error' => $e->getMessage()]);
            }
        }

        // 降级: 使用免费 API (仅用于开发/测试)
        if (app()->environment('local', 'testing')) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(3)
                    ->get("http://ip-api.com/json/{$ip}?fields=countryCode");
                if ($response->successful()) {
                    $code = $response->json('countryCode');
                    if ($code && $code !== '--') return $code;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return null;
    }

    /**
     * 获取 License 的地理围栏配置
     */
    public function getConfig(int $licenseId): ?array
    {
        $restriction = LicenseRestriction::active()->byType('geo_fence')->forLicense($licenseId)->first();
        if (!$restriction) return null;

        return [
            'id' => $restriction->id,
            'is_active' => $restriction->is_active,
            'action' => $restriction->action,
            'allowed_countries' => $restriction->allowed_countries ?? [],
            'blocked_countries' => $restriction->blocked_countries ?? [],
            'unknown_location_action' => $restriction->unknown_location_action ?? 'allow',
            'description' => $restriction->description,
            'approved_at' => $restriction->approved_at?->toIso8601String(),
        ];
    }

    /**
     * 保存地理围栏配置
     */
    public function saveConfig(int $licenseId, array $data, ?int $userId = null): LicenseRestriction
    {
        $restriction = LicenseRestriction::byType('geo_fence')->forLicense($licenseId)->first();

        if ($restriction) {
            $restriction->update([
                'is_active' => $data['is_active'] ?? $restriction->is_active,
                'action' => $data['action'] ?? $restriction->action,
                'allowed_countries' => $data['allowed_countries'] ?? $restriction->allowed_countries,
                'blocked_countries' => $data['blocked_countries'] ?? $restriction->blocked_countries,
                'unknown_location_action' => $data['unknown_location_action'] ?? $restriction->unknown_location_action,
                'description' => $data['description'] ?? $restriction->description,
            ]);
        } else {
            $restriction = LicenseRestriction::create([
                'restrictable_type' => 'license',
                'restrictable_id' => $licenseId,
                'type' => 'geo_fence',
                'is_active' => $data['is_active'] ?? true,
                'action' => $data['action'] ?? 'block',
                'allowed_countries' => $data['allowed_countries'] ?? [],
                'blocked_countries' => $data['blocked_countries'] ?? [],
                'unknown_location_action' => $data['unknown_location_action'] ?? 'allow',
                'description' => $data['description'] ?? '',
                'created_by' => $userId,
            ]);
        }

        return $restriction;
    }

    /**
     * 删除地理围栏配置
     */
    public function deleteConfig(int $licenseId): bool
    {
        return LicenseRestriction::byType('geo_fence')->forLicense($licenseId)->delete() > 0;
    }

    /**
     * 获取国家列表
     */
    public function getCountries(): array
    {
        return config('license-restrictions.countries', []);
    }

    /**
     * 记录并返回
     */
    protected function logAndReturn(int $licenseId, string $ip, ?string $country, string $result, ?string $reason, $restriction): array
    {
        if (config('license-restrictions.common.log_all_checks') || $result === 'blocked') {
            LicenseRestrictionLog::create([
                'restrictable_type' => 'license',
                'restrictable_id' => $licenseId,
                'type' => 'geo_fence',
                'result' => $result,
                'ip_address' => $ip,
                'country' => $country,
                'reason' => $reason,
            ]);
        }

        if ($result === 'blocked') {
            Log::warning('地理围栏拦截', [
                'license_id' => $licenseId,
                'ip' => $ip,
                'country' => $country,
                'reason' => $reason,
            ]);
        }

        return [
            'allowed' => $result === 'allowed',
            'reason' => $reason,
            'result' => $result,
            'country' => $country,
        ];
    }
}
