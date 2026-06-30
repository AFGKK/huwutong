<?php

namespace App\Services;

use App\Models\SensitiveWord;
use Illuminate\Support\Facades\Cache;

class SensitiveWordService
{
    protected array $trie = [];
    protected array $words = [];
    protected bool $loaded = false;

    /**
     * 构建 AC 自动机 (Trie)
     */
    protected function buildTrie(): void
    {
        $this->trie = [];
        $this->words = [];

        $words = SensitiveWord::active()->get(['word', 'replacement']);
        foreach ($words as $w) {
            $word = mb_strtolower($w->word);
            $this->words[$word] = $w->replacement ?? '***';
            $node = &$this->trie;
            $len = mb_strlen($word);
            for ($i = 0; $i < $len; $i++) {
                $char = mb_substr($word, $i, 1);
                if (!isset($node[$char])) {
                    $node[$char] = [];
                }
                $node = &$node[$char];
            }
            $node['_end'] = true;
        }
        $this->loaded = true;
    }

    /**
     * 检查文本是否包含敏感词
     * @return array{hasSensitive: bool, matched: array, replaced: string}
     */
    public function check(string $text): array
    {
        if (!$this->loaded) {
            // 尝试从缓存加载构建好的 Trie
            $cached = Cache::get('sensitive_word_trie');
            if ($cached) {
                $this->trie = $cached['trie'] ?? [];
                $this->words = $cached['words'] ?? [];
                $this->loaded = true;
            } else {
                $this->buildTrie();
                Cache::put('sensitive_word_trie', [
                    'trie' => $this->trie,
                    'words' => $this->words,
                ], now()->addMinutes(30));
            }
        }
        if (empty($this->trie)) {
            return ['hasSensitive' => false, 'matched' => [], 'replaced' => $text];
        }

        $matched = [];
        $result = $text;
        $len = mb_strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $node = $this->trie;
            $matchLen = 0;
            for ($j = $i; $j < $len; $j++) {
                $char = mb_substr($text, $j, 1);
                if (!isset($node[$char])) break;
                $node = $node[$char];
                $matchLen++;
                if (!empty($node['_end'])) {
                    $matchedWord = mb_substr($text, $i, $matchLen);
                    $replacement = $this->words[mb_strtolower($matchedWord)] ?? '***';
                    $matched[] = $matchedWord;
                    // 使用 mb_* 函数进行替换
                    $result = mb_substr($result, 0, $i) . $replacement . mb_substr($result, $i + mb_strlen($matchedWord));
                    $i = $j;
                    break;
                }
            }
        }

        return [
            'hasSensitive' => count($matched) > 0,
            'matched' => array_values(array_unique($matched)),
            'replaced' => $result,
        ];
    }

    /**
     * 清除缓存（添加/编辑/删除敏感词后调用）
     */
    public static function clearCache(): void
    {
        Cache::forget('sensitive_word_trie');
    }
}
