<?php

namespace App\Services;

use Illuminate\Support\Str;

class FingerprintService
{
    /**
     * 当前指纹版本
     */
    const int CURRENT_VERSION = 2;

    /**
     * 加盐常量（生产环境应从配置/环境变量读取）
     */
    const string FINGERPRINT_SALT = 'hwt-fingerprint-salt-2026';

    /**
     * 生成设备指纹
     *
     * 算法：SHA256( SALT + '|' + MAC + '|' + CPU_ID + '|' + MOTHERBOARD + '|' + DISK_SN + '|' + SYSTEM_UUID )
     * 输出: {version}:{hash_hex}
     *
     * @param array $components 设备组件信息
     * @param int   $version    指纹版本
     * @return string 指纹字符串 (e.g. "2:a1b2c3d4...")
     */
    public function generate(array $components, int $version = self::CURRENT_VERSION): string
    {
        $normalized = $this->normalizeComponents($components, $version);
        $raw = implode('|', [
            self::FINGERPRINT_SALT,
            $normalized['mac'],
            $normalized['cpu_id'],
            $normalized['motherboard'],
            $normalized['disk_sn'],
            $normalized['system_uuid'],
        ]);

        $hash = hash('sha256', $raw);

        return "{$version}:{$hash}";
    }

    /**
     * 从指纹字符串中提取版本号
     */
    public function extractVersion(string $fingerprint): ?int
    {
        if (preg_match('/^(\d+):/', $fingerprint, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * 校验指纹格式是否合法
     */
    public function isValidFormat(string $fingerprint): bool
    {
        return (bool) preg_match('/^\d+:[a-f0-9]{64}$/', $fingerprint);
    }

    /**
     * 规范化组件数据（V1/V2 版本差异处理）
     */
    public function normalizeComponents(array $components, int $version = self::CURRENT_VERSION): array
    {
        if ($version === 1) {
            return [
                'mac' => $this->normalizeMac($components['mac'] ?? ''),
                'cpu_id' => $this->normalizeCpuId($components['cpu_id'] ?? ''),
                'motherboard' => $this->normalizeMotherboard($components['motherboard'] ?? ''),
                'disk_sn' => $this->normalizeDiskSn($components['disk_sn'] ?? ''),
                'system_uuid' => $this->normalizeSystemUuid($components['system_uuid'] ?? ''),
            ];
        }

        // V2+ 增强：去除空格、统一大小写、加入更多规范化
        return [
            'mac' => $this->normalizeMacV2($components['mac'] ?? ''),
            'cpu_id' => $this->normalizeCpuIdV2($components['cpu_id'] ?? ''),
            'motherboard' => $this->normalizeMotherboardV2($components['motherboard'] ?? ''),
            'disk_sn' => $this->normalizeDiskSnV2($components['disk_sn'] ?? ''),
            'system_uuid' => $this->normalizeSystemUuidV2($components['system_uuid'] ?? ''),
        ];
    }

    // ─── V1 规范化（基础） ───

    protected function normalizeMac(string $mac): string
    {
        return strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
    }

    protected function normalizeCpuId(string $cpuId): string
    {
        return strtoupper(trim($cpuId));
    }

    protected function normalizeMotherboard(string $mb): string
    {
        return strtoupper(trim(preg_replace('/\s+/', '', $mb)));
    }

    protected function normalizeDiskSn(string $sn): string
    {
        return strtoupper(trim($sn));
    }

    protected function normalizeSystemUuid(string $uuid): string
    {
        return strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $uuid));
    }

    // ─── V2 规范化（增强稳定性） ───

    protected function normalizeMacV2(string $mac): string
    {
        // 去除所有分隔符，统一大写
        $clean = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
        // 如果 MAC 全零或太短（<12 chars），返回空表示不可用
        if (strlen($clean) < 12 || preg_match('/^0+$/', $clean)) {
            return '';
        }
        return $clean;
    }

    protected function normalizeCpuIdV2(string $cpuId): string
    {
        $clean = strtoupper(trim(preg_replace('/\s+/', ' ', $cpuId)));
        // 过滤掉常见的虚拟化 CPU 占位符
        if (empty($clean) || $clean === 'N/A' || $clean === 'TO BE FILLED BY OEM') {
            return '';
        }
        return $clean;
    }

    protected function normalizeMotherboardV2(string $mb): string
    {
        $clean = strtoupper(trim(preg_replace('/\s+/', '', $mb)));
        if (empty($clean) || $clean === 'N/A' || str_contains($clean, 'TOBEFILLEDBYOEM')) {
            return '';
        }
        return $clean;
    }

    protected function normalizeDiskSnV2(string $sn): string
    {
        $clean = strtoupper(trim($sn));
        if (empty($clean) || $clean === 'N/A' || preg_match('/^0+$/', $clean)) {
            return '';
        }
        return $clean;
    }

    protected function normalizeSystemUuidV2(string $uuid): string
    {
        $clean = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $uuid));
        // 过滤全零 UUID
        if (preg_match('/^0+$/', $clean)) {
            return '';
        }
        return $clean;
    }
}
