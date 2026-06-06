<?php

namespace App\Services;

use App\Enums\LicenseType;
use Illuminate\Support\Str;

class KeyGenerator
{
    /**
     * License Key 前缀映射
     */
    const array PREFIX_MAP = [
        'trial' => 'HWT-TRIAL',
        'standard' => 'HWT-STD',
        'enterprise' => 'HWT-ENT',
        'development' => 'HWT-DEV',
    ];

    /**
     * 生成不可枚举的 License Key
     * 格式: {PREFIX}-{RANDOM_16_HEX}-{CHECKSUM_4_HEX}
     * 示例: HWT-ENT-A3F2C8D1E9B07456-1A2B
     */
    public function generate(string $type = 'standard'): string
    {
        $prefix = self::PREFIX_MAP[$type] ?? 'HWT';
        $random = strtoupper(bin2hex(random_bytes(8))); // 16 hex chars
        $checksum = $this->checksum($prefix.$random);

        return "{$prefix}-{$random}-{$checksum}";
    }

    /**
     * 批量生成 License Key
     */
    public function generateBatch(string $type, int $count): array
    {
        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $keys[] = $this->generate($type);
        }
        return $keys;
    }

    /**
     * 校验 License Key 格式是否合法
     */
    public function validateFormat(string $licenseKey): bool
    {
        if (! preg_match('/^(HWT(?:-TRIAL|-STD|-ENT|-DEV)?)-([A-F0-9]{16})-([A-F0-9]{4})$/', $licenseKey, $matches)) {
            return false;
        }

        $prefix = $matches[1];
        $random = $matches[2];
        $checksum = $matches[3];

        // 校验 checksum
        return $this->checksum($prefix.$random) === $checksum;
    }

    /**
     * 简单的 CRC-like 校验和
     */
    protected function checksum(string $input): string
    {
        $hash = strtoupper(md5($input));
        return substr($hash, 0, 4);
    }
}
