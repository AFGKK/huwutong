<?php

namespace App\Services;

class FingerprintMatcher
{
    /**
     * 组件键名（用于分组件比对）
     */
    const array COMPONENT_KEYS = ['mac', 'cpu_id', 'motherboard', 'disk_sn', 'system_uuid'];

    /**
     * 阈值配置：最少匹配组件数
     */
    const int MIN_MATCH_V1 = 2; // V1: 5取2
    const int MIN_MATCH_V2 = 3; // V2: 5取3（更严格）

    public function __construct(
        protected FingerprintService $fingerprintService,
    ) {}

    /**
     * 3取2宽容匹配：比较两个设备指纹是否匹配
     *
     * 策略：
     * 1. 解析两个指纹的原始组件
     * 2. 逐组件比对（规范化后）
     * 3. 如果匹配组件数 >= 阈值（V1=2, V2=3）则认为匹配
     *
     * @param array $componentsA 设备A的原始组件
     * @param array $componentsB 设备B的原始组件
     * @param int   $version     指纹版本
     * @return array ['matched' => bool, 'score' => int, 'details' => [...]]
     */
    public function match(array $componentsA, array $componentsB, int $version = FingerprintService::CURRENT_VERSION): array
    {
        $normA = $this->fingerprintService->normalizeComponents($componentsA, $version);
        $normB = $this->fingerprintService->normalizeComponents($componentsB, $version);

        $matched = [];
        $score = 0;

        foreach (self::COMPONENT_KEYS as $key) {
            $valA = $normA[$key];
            $valB = $normB[$key];

            // 空值不计入匹配
            if (empty($valA) || empty($valB)) {
                $matched[$key] = 'skipped';
                continue;
            }

            if ($valA === $valB) {
                $matched[$key] = 'match';
                $score++;
            } else {
                $matched[$key] = 'mismatch';
            }
        }

        $threshold = $version >= 2 ? self::MIN_MATCH_V2 : self::MIN_MATCH_V1;

        return [
            'matched' => $score >= $threshold,
            'score' => $score,
            'threshold' => $threshold,
            'total_compared' => count(array_filter($matched, fn ($v) => $v !== 'skipped')),
            'details' => $matched,
        ];
    }

    /**
     * 快速判断两个指纹字符串是否匹配（使用原始组件存储）
     *
     * @param array $storedComponents 数据库中存储的原始组件
     * @param array $incomingComponents 本次请求的原始组件
     * @return bool
     */
    public function isMatch(array $storedComponents, array $incomingComponents): bool
    {
        $v1 = $this->match($storedComponents, $incomingComponents, 1);
        $v2 = $this->match($storedComponents, $incomingComponents, 2);

        // 任意版本匹配即认为匹配（向后兼容）
        return $v1['matched'] || $v2['matched'];
    }

    /**
     * 计算两个指纹的相似度百分比
     */
    public function similarity(array $componentsA, array $componentsB, int $version = FingerprintService::CURRENT_VERSION): float
    {
        $result = $this->match($componentsA, $componentsB, $version);

        if ($result['total_compared'] === 0) {
            return 0.0;
        }

        return round(($result['score'] / $result['total_compared']) * 100, 2);
    }
}
