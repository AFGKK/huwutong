<?php

namespace App\Services;

use App\Models\CustomEmoji;
use Illuminate\Support\Facades\Cache;

class EmojiService
{
    protected ?array $emojiMap = null;

    /**
     * 获取所有自定义 emoji 的 shortcode -> image_url 映射
     */
    public function getEmojiMap(): array
    {
        if ($this->emojiMap !== null) {
            return $this->emojiMap;
        }

        $this->emojiMap = Cache::remember('custom_emoji_map', 300, function () {
            $emojis = CustomEmoji::active()->get(['shortcode', 'image_url', 'aliases']);
            $map = [];
            foreach ($emojis as $emoji) {
                $map[$emoji->shortcode] = $emoji->image_url;
                foreach ($emoji->getAliasList() as $alias) {
                    if ($alias) {
                        $map[$alias] = $emoji->image_url;
                    }
                }
            }
            return $map;
        });

        return $this->emojiMap;
    }

    /**
     * 清除缓存
     */
    public static function clearCache(): void
    {
        Cache::forget('custom_emoji_map');
    }

    /**
     * 替换文本中的 :shortcode: 为 HTML <img> 标签
     */
    public function replaceShortcodes(string $text): string
    {
        $map = $this->getEmojiMap();
        if (empty($map)) {
            return $text;
        }

        // 构建正则：匹配 :shortcode: 格式
        $shortcodes = array_keys($map);
        $pattern = '/:(' . preg_quote(implode('|', $shortcodes), '/') . '):/';

        return preg_replace_callback($pattern, function ($matches) use ($map) {
            $code = $matches[1];
            $url = $map[$code] ?? null;
            if (!$url) return $matches[0];
            return '<img src="' . e($url) . '" alt=":' . e($code) . ':" class="custom-emoji" title=":' . e($code) . ':" />';
        }, $text);
    }

    /**
     * 从文本中提取所有 :shortcode: 匹配
     */
    public function extractShortcodes(string $text): array
    {
        preg_match_all('/:([a-zA-Z0-9_\x{4e00}-\x{9fa5}]+):/u', $text, $matches);
        return $matches[1] ?? [];
    }

    /**
     * 解析文本中的 :shortcode:，返回匹配的 emoji 列表
     */
    public function parseEmojis(string $text): array
    {
        $codes = $this->extractShortcodes($text);
        if (empty($codes)) return [];

        $map = $this->getEmojiMap();
        $result = [];
        foreach ($codes as $code) {
            if (isset($map[$code])) {
                $result[] = [
                    'shortcode' => $code,
                    'image_url' => $map[$code],
                ];
            }
        }
        return $result;
    }
}
