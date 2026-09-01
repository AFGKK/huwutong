<?php

namespace App\Services;

use App\Models\LicenseRestriction;
use App\Models\LicenseRestrictionLog;
use Illuminate\Support\Facades\Log;

/**
 * IP 范围限制服务 (M2-92)
 *
 * License绑定CIDR白名单：仅允许指定IP段激活
 */
class IpRestrictionService
{
    /**
     * 验证 IP 是否被允许
     */
    public function check(int $licenseId, string $ipAddress, string $context = 'activate'): array
    {
        $restriction = LicenseRestriction::active()
            ->byType('ip_range')
            ->forLicense($licenseId)
            ->first();

        if (!$restriction) {
            // 未配置限制时默认允许
            return ['allowed' => true, 'reason' => null];
        }

        // 检查 IP 黑名单（先于白名单）
        if ($this->ipInList($ipAddress, $restriction->ip_blacklist ?? [])) {
            return $this->logAndReturn($licenseId, $ipAddress, 'blocked', 'IP在黑名单中', $restriction);
        }

        // 检查 IP 白名单
        if (!empty($restriction->ip_whitelist) && $this->ipInList($ipAddress, $restriction->ip_whitelist)) {
            return $this->logAndReturn($licenseId, $ipAddress, 'allowed', 'IP在白名单中', $restriction);
        }

        // 检查 CIDR 范围
        if (!empty($restriction->ip_ranges)) {
            if ($this->ipInRanges($ipAddress, $restriction->ip_ranges)) {
                return $this->logAndReturn($licenseId, $ipAddress, 'allowed', 'IP在允许范围内', $restriction);
            }
            if ($restriction->action === 'block') {
                return $this->logAndReturn($licenseId, $ipAddress, 'blocked', 'IP不在允许范围内', $restriction);
            }
            if ($restriction->action === 'audit') {
                return $this->logAndReturn($licenseId, $ipAddress, 'audited', 'IP不在允许范围内(仅审计)', $restriction);
            }
        }

        // 无 CIDR 规则但有 whitelist 命中前面已返回
        // 默认按 action 处理
        if ($restriction->action === 'block') {
            return $this->logAndReturn($licenseId, $ipAddress, 'blocked', 'IP范围限制', $restriction);
        }

        return $this->logAndReturn($licenseId, $ipAddress, 'allowed', null, $restriction);
    }

    /**
     * 检查 IP 是否在列表中
     */
    protected function ipInList(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            $entry = trim($entry);
            if (str_contains($entry, '/')) {
                if ($this->ipInCidr($ip, $entry)) return true;
            } elseif ($entry === $ip) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查 IP 是否在 CIDR 范围列表中
     */
    protected function ipInRanges(string $ip, array $ranges): bool
    {
        foreach ($ranges as $cidr) {
            if ($this->ipInCidr($ip, trim($cidr))) return true;
        }
        return false;
    }

    /**
     * CIDR 匹配
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) return false;

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InCidr($ip, $subnet, $bits);
        }

        return false;
    }

    /**
     * IPv6 CIDR 匹配
     */
    protected function ipv6InCidr(string $ip, string $subnet, int $bits): bool
    {
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) return false;

        $mask = str_repeat("\xff", intdiv($bits, 8));
        if ($bits % 8 !== 0) {
            $mask .= chr(0xff << (8 - ($bits % 8)) & 0xff);
        }
        $mask = str_pad($mask, 16, "\x00");

        return ($ipBin & $mask) === ($subnetBin & $mask);
    }

    /**
     * 获取 License 的 IP 限制配置
     */
    public function getConfig(int $licenseId): ?array
    {
        $restriction = LicenseRestriction::active()->byType('ip_range')->forLicense($licenseId)->first();
        if (!$restriction) return null;

        return [
            'id' => $restriction->id,
            'is_active' => $restriction->is_active,
            'action' => $restriction->action,
            'ip_ranges' => $restriction->ip_ranges ?? [],
            'ip_whitelist' => $restriction->ip_whitelist ?? [],
            'ip_blacklist' => $restriction->ip_blacklist ?? [],
            'description' => $restriction->description,
            'approved_at' => $restriction->approved_at?->toIso8601String(),
        ];
    }

    /**
     * 保存 IP 限制配置
     */
    public function saveConfig(int $licenseId, array $data, ?int $userId = null): LicenseRestriction
    {
        $restriction = LicenseRestriction::byType('ip_range')->forLicense($licenseId)->first();

        if ($restriction) {
            $restriction->update([
                'is_active' => $data['is_active'] ?? $restriction->is_active,
                'action' => $data['action'] ?? $restriction->action,
                'ip_ranges' => $data['ip_ranges'] ?? $restriction->ip_ranges,
                'ip_whitelist' => $data['ip_whitelist'] ?? $restriction->ip_whitelist,
                'ip_blacklist' => $data['ip_blacklist'] ?? $restriction->ip_blacklist,
                'description' => $data['description'] ?? $restriction->description,
            ]);
        } else {
            $restriction = LicenseRestriction::create([
                'restrictable_type' => 'license',
                'restrictable_id' => $licenseId,
                'type' => 'ip_range',
                'is_active' => $data['is_active'] ?? true,
                'action' => $data['action'] ?? 'block',
                'ip_ranges' => $data['ip_ranges'] ?? [],
                'ip_whitelist' => $data['ip_whitelist'] ?? [],
                'ip_blacklist' => $data['ip_blacklist'] ?? [],
                'description' => $data['description'] ?? '',
                'created_by' => $userId,
            ]);
        }

        return $restriction;
    }

    /**
     * 删除 IP 限制配置
     */
    public function deleteConfig(int $licenseId): bool
    {
        return LicenseRestriction::byType('ip_range')->forLicense($licenseId)->delete() > 0;
    }

    /**
     * 记录并返回结果
     */
    protected function logAndReturn(int $licenseId, string $ip, string $result, ?string $reason, $restriction): array
    {
        if (config('license-restrictions.common.log_all_checks') || $result === 'blocked') {
            LicenseRestrictionLog::create([
                'restrictable_type' => 'license',
                'restrictable_id' => $licenseId,
                'type' => 'ip_range',
                'result' => $result,
                'ip_address' => $ip,
                'reason' => $reason,
            ]);
        }

        if ($result === 'blocked') {
            Log::warning('IP范围限制拦截', [
                'license_id' => $licenseId,
                'ip' => $ip,
                'reason' => $reason,
            ]);
        }

        return [
            'allowed' => in_array($result, ['allowed', 'audited'], true),
            'reason' => $reason,
            'result' => $result,
        ];
    }
}
