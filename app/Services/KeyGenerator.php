<?php

namespace App\Services;

use App\Enums\LicenseType;
use App\Services\Hsm\HsmService;
use Illuminate\Support\Str;

class KeyGenerator
{
    /**
     * License Key 前缀映射
     */
    const array PREFIX_MAP = [
        'trial' => 'HWT-TRIAL',
        'standard' => 'HWT-STD',
        'professional' => 'HWT-PRO',
        'enterprise' => 'HWT-ENT',
        'development' => 'HWT-DEV',
    ];

    const array REVERSE_PREFIX_MAP = [
        'HWT-TRIAL' => 'trial',
        'HWT-STD' => 'standard',
        'HWT-PRO' => 'professional',
        'HWT-ENT' => 'enterprise',
        'HWT-DEV' => 'development',
    ];

    private HsmService $hsm;

    public function __construct(?HsmService $hsm = null)
    {
        $this->hsm = $hsm ?? app(HsmService::class);
    }

    /**
     * 生成不可枚举的 License Key
     * 格式: {PREFIX}-{RANDOM_16_HEX}-{CHECKSUM_4_HEX}
     */
    public function generate(string $type = 'standard'): string
    {
        $prefix = self::PREFIX_MAP[$type] ?? 'HWT';
        $random = strtoupper(bin2hex(random_bytes(8)));
        $checksum = $this->checksum($prefix.$random);

        return "{$prefix}-{$random}-{$checksum}";
    }

    /**
     * 生成带 HSM 签名的 License Key
     * 返回格式: {KEY}:{HSM_SIGNATURE_HEX}:{KEY_ID}
     */
    public function generateSigned(string $type = 'standard', string $keyLabel = 'license-v1'): string
    {
        $licenseKey = $this->generate($type);

        if (!$this->hsm->isEnabled()) {
            return $licenseKey;
        }

        $result = $this->hsm->signLicenseKey($licenseKey, $keyLabel);
        return "{$licenseKey}:{$result['signature']}:{$result['key_id']}";
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
        if (! preg_match('/^(HWT(?:-TRIAL|-STD|-PRO|-ENT|-DEV)?)-([A-F0-9]{16})-([A-F0-9]{4})$/', $licenseKey, $matches)) {
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

    /**
     * 从 License Key 前缀推断类型
     */
    public function inferType(string $licenseKey): ?string
    {
        foreach (self::REVERSE_PREFIX_MAP as $prefix => $type) {
            if (str_starts_with($licenseKey, $prefix)) {
                return $type;
            }
        }
        return null;
    }

    /**
     * 获取 License Key 的可读类型名称
     */
    public function getReadableType(string $licenseKey): string
    {
        $map = [
            'HWT-TRIAL' => '试用版',
            'HWT-STD' => '标准版',
            'HWT-PRO' => '专业版',
            'HWT-ENT' => '企业版',
            'HWT-DEV' => '开发版',
        ];

        foreach ($map as $prefix => $label) {
            if (str_starts_with($licenseKey, $prefix)) {
                return $label;
            }
        }
        return '未知';
    }
}
