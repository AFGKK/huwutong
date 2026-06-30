<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Facades\DB;

/**
 * License Key 可读前缀格式化服务
 *
 * 确保 License Key 以 HWT-ENT-xxx / HWT-PRO-xxx / HWT-TRIAL-xxx 等格式呈现，
 * 运营人员可以一眼识别 License 类型。
 */
class KeyPrefixFormatter
{
    public function __construct(
        protected KeyGenerator $keyGenerator,
    ) {}

    /**
     * 格式化已知类型的 License Key
     */
    public function format(License $license): ?string
    {
        $type = $license->type;
        $prefix = KeyGenerator::PREFIX_MAP[$type] ?? null;

        if (!$prefix) {
            return null;
        }

        $key = $license->license_key;

        // 如果已经以此前缀开头，则无需修改
        if (str_starts_with($key, $prefix)) {
            return $key;
        }

        // 去掉旧前缀，只保留随机部分+校验码
        // 旧格式可能是: old_prefix-random-checksum 或 plain key
        $suffix = $this->extractSuffix($key);

        if (!$suffix) {
            // 无法提取后缀，重新生成
            return $this->keyGenerator->generate($type);
        }

        return "{$prefix}-{$suffix}";
    }

    /**
     * 批量格式化指定 License
     */
    public function formatBatch(array $licenses): array
    {
        $results = [];
        foreach ($licenses as $license) {
            $results[$license->id] = $this->format($license);
        }
        return $results;
    }

    /**
     * 执行数据库迁移：更新所有 License Key 为可读前缀格式
     */
    public function migrateAll(): array
    {
        $stats = ['total' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        License::chunk(100, function ($licenses) use (&$stats) {
            foreach ($licenses as $license) {
                $stats['total']++;
                try {
                    $formatted = $this->format($license);
                    if ($formatted && $formatted !== $license->license_key) {
                        $oldKey = $license->license_key;
                        $license->update(['license_key' => $formatted]);

                        // 更新关联表的 license_key 引用
                        $this->updateRelatedTables($oldKey, $formatted);

                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors'][] = "License #{$license->id}: {$e->getMessage()}";
                }
            }
        });

        return $stats;
    }

    /**
     * 获取 Key 的格式标签
     */
    public function getFormatLabel(string $licenseKey): string
    {
        return $this->keyGenerator->getReadableType($licenseKey);
    }

    /**
     * 提取 Key 的后缀部分（随机+校验码）
     */
    protected function extractSuffix(string $key): ?string
    {
        // 匹配 HWT-XXX-RANDOM-CHECKSUM 格式
        if (preg_match('/^HWT-[A-Z]+-([A-F0-9]{16}-[A-F0-9]{4})$/', $key, $m)) {
            return $m[1];
        }

        // 匹配纯 RANDOM-CHECKSUM 格式
        if (preg_match('/^([A-F0-9]{16}-[A-F0-9]{4})$/', $key, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * 更新关联表的 license_key 引用
     */
    protected function updateRelatedTables(string $oldKey, string $newKey): void
    {
        $tables = [
            'license_verification_logs' => 'license_key',
            'tamper_events' => 'license_key',
            'audit_logs' => 'license_key',
        ];

        foreach ($tables as $table => $column) {
            try {
                DB::table($table)->where($column, $oldKey)->update([$column => $newKey]);
            } catch (\Exception $e) {
                // 表可能不存在，忽略
            }
        }
    }
}
