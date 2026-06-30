<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AI-011: 语义缓存服务
 *
 * 对相似的 LLM 请求自动命中缓存，降低 API 成本和延迟。
 * 使用文本相似度（简单编辑距离/Jaccard）判断缓存命中，
 * 无需外部 Embedding 服务即可工作。
 */
class SemanticCacheService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'semantic_cache:';

    /**
     * 默认缓存 TTL（小时）
     */
    const DEFAULT_TTL_HOURS = 24;

    /**
     * 相似度阈值（0~1），高于此值视为命中
     */
    const SIMILARITY_THRESHOLD = 0.85;

    /**
     * 最大缓存条目数
     */
    const MAX_ENTRIES = 500;

    /**
     * 从缓存获取
     *
     * @param string $prompt  用户输入/请求文本
     * @param string $context 可选上下文（如对话ID前缀）
     * @return array|null ['content' => string, 'cached_at' => string] 或 null
     */
    public function get(string $prompt, string $context = ''): ?array
    {
        $bucket = $this->bucket($context);
        $cached = Cache::get($bucket, []);

        if (empty($cached)) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($cached as $entry) {
            $score = $this->similarity($prompt, $entry['prompt'] ?? '');
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $entry;
            }
        }

        if ($best && $bestScore >= self::SIMILARITY_THRESHOLD) {
            Log::debug('[SemanticCache] HIT', [
                'score' => round($bestScore, 4),
                'context' => $context ?: '(global)',
                'prompt_len' => mb_strlen($prompt),
            ]);
            return [
                'content' => $best['content'],
                'cached_at' => $best['cached_at'],
                'similarity' => $bestScore,
            ];
        }

        return null;
    }

    /**
     * 写入缓存
     */
    public function put(string $prompt, string $content, string $context = '', int $ttlHours = self::DEFAULT_TTL_HOURS): void
    {
        $bucket = $this->bucket($context);
        $cached = Cache::get($bucket, []);

        // 如果已存在相同 prompt，更新
        $found = false;
        foreach ($cached as $i => $entry) {
            if ($entry['prompt'] === $prompt) {
                $cached[$i]['content'] = $content;
                $cached[$i]['cached_at'] = now()->toIso8601String();
                $found = true;
                break;
            }
        }

        if (!$found) {
            // 限制条目数
            if (count($cached) >= self::MAX_ENTRIES) {
                array_shift($cached);
            }
            $cached[] = [
                'prompt' => $prompt,
                'content' => $content,
                'cached_at' => now()->toIso8601String(),
            ];
        }

        Cache::put($bucket, $cached, now()->addHours($ttlHours));
    }

    /**
     * 清除指定上下文的缓存
     */
    public function clear(string $context = ''): void
    {
        Cache::forget($this->bucket($context));
    }

    /**
     * 获取缓存统计
     */
    public function stats(string $context = ''): array
    {
        $bucket = $this->bucket($context);
        $cached = Cache::get($bucket, []);
        return [
            'total_entries' => count($cached),
            'context' => $context ?: 'global',
            'bucket_key' => $bucket,
        ];
    }

    /**
     * 计算两个字符串的文本相似度（Jaccard + 编辑距离混合）
     */
    protected function similarity(string $a, string $b): float
    {
        if ($a === $b) return 1.0;
        if (empty($a) || empty($b)) return 0.0;

        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));

        // Jaccard 相似度（基于字符双字母组）
        $bigramsA = $this->bigrams($a);
        $bigramsB = $this->bigrams($b);

        $intersection = array_intersect($bigramsA, $bigramsB);
        $union = array_unique(array_merge($bigramsA, $bigramsB));

        $jaccard = count($union) > 0 ? count($intersection) / count($union) : 0;

        // 编辑距离相似度（归一化）
        $len = max(mb_strlen($a), mb_strlen($b));
        $lev = $len > 0 ? 1 - (levenshtein($a, $b) / $len) : 0;

        // 加权混合
        return 0.6 * $jaccard + 0.4 * $lev;
    }

    /**
     * 获取字符双字母组
     */
    protected function bigrams(string $s): array
    {
        $chars = mb_str_split($s);
        $result = [];
        for ($i = 0; $i < count($chars) - 1; $i++) {
            $result[] = $chars[$i] . ($chars[$i + 1] ?? '');
        }
        return $result;
    }

    /**
     * 获取缓存桶键名
     */
    protected function bucket(string $context): string
    {
        $suffix = $context ? ':' . md5($context) : '';
        return self::CACHE_PREFIX . 'bucket' . $suffix;
    }
}
