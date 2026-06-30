<?php

namespace App\Services;

use App\Models\ForumTag;
use Illuminate\Support\Facades\Log;

/**
 * 智能标签推荐服务
 * 根据帖子内容自动推荐标签
 */
class TagSuggestionService
{
    protected LlmService $llm;

    // 热门标签缓存（避免频繁查询）
    protected ?array $hotTags = null;

    public function __construct(LlmService $llm)
    {
        $this->llm = $llm;
    }

    /**
     * 根据内容推荐标签
     *
     * @return array<int, array{name: string, score: float}>
     */
    public function suggest(string $content, int $limit = 5): array
    {
        if (empty(trim($content))) {
            return [];
        }

        $plainText = strip_tags($content);
        if (mb_strlen($plainText) < 4) {
            return [];
        }

        // 1. 关键词提取
        $keywords = $this->extractKeywords($plainText);

        // 2. 匹配已有标签
        $matched = $this->matchExistingTags($keywords);

        // 3. 如果匹配不足，尝试 AI 生成
        if (count($matched) < $limit) {
            $aiTags = $this->generateWithAI($plainText);
            foreach ($aiTags as $tag) {
                $name = trim($tag);
                if (empty($name)) continue;
                $exists = false;
                foreach ($matched as $m) {
                    if ($m['name'] === $name) { $exists = true; break; }
                }
                if (!$exists) {
                    $matched[] = ['name' => $name, 'score' => 0.5];
                }
            }
        }

        // 4. 按分数排序取 top N
        usort($matched, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($matched, 0, $limit);
    }

    /**
     * 提取关键词（基于词频+长度加权）
     */
    protected function extractKeywords(string $text): array
    {
        // 中文/英文分词
        $words = [];
        // 提取中文词组（2-4字）
        preg_match_all('/[\x{4e00}-\x{9fa5}]{2,4}/u', $text, $matches);
        foreach ($matches[0] ?? [] as $w) {
            $words[] = $w;
        }
        // 提取英文单词
        preg_match_all('/[a-zA-Z]{3,}/', $text, $enMatches);
        foreach ($enMatches[0] ?? [] as $w) {
            $words[] = mb_strtolower($w);
        }

        // 停用词过滤
        $stopWords = ['可以', '什么', '怎么', '这个', '那个', '一个', '没有', '不是', '因为', '所以',
            '但是', '如果', '而且', '或者', '虽然', '然后', '就是', '自己', '知道', '觉得', '应该',
            '已经', '比较', '这些', '那些', '时候', '现在', '这样', '那个', '我们', '你们', '他们',
            'the', 'this', 'that', 'with', 'from', 'have', 'been', 'will'];

        $filtered = array_filter($words, fn($w) => !in_array($w, $stopWords) && mb_strlen($w) >= 2);

        // 词频统计
        $freq = array_count_values($filtered);
        arsort($freq);

        // 加权：长度越长权重越高
        $scored = [];
        foreach ($freq as $word => $count) {
            $lengthWeight = 1 + (mb_strlen($word) / 10);
            $scored[$word] = $count * $lengthWeight;
        }
        arsort($scored);

        return $scored;
    }

    /**
     * 匹配数据库已有标签
     */
    protected function matchExistingTags(array $keywords): array
    {
        $this->loadHotTags();

        $matched = [];
        foreach ($this->hotTags as $tag) {
            $name = $tag['name'];
            $score = 0;

            foreach ($keywords as $word => $weight) {
                if (mb_strpos($name, $word) !== false || mb_strpos($word, $name) !== false) {
                    $score += $weight * 0.8;
                }
                // 模糊匹配
                $similar = similar_text($name, $word, $percent);
                if ($percent > 60) {
                    $score += ($percent / 100) * $weight;
                }
            }

            if ($score > 0) {
                $matched[] = [
                    'name' => $name,
                    'score' => round($score, 2),
                ];
            }
        }

        return $matched;
    }

    /**
     * AI 生成标签
     */
    protected function generateWithAI(string $text): array
    {
        try {
            $prompt = "分析以下内容，给出3-5个最相关的中文标签，每个标签2-4个字。\n只返回标签，用逗号分隔，不要任何解释。\n\n内容：" . mb_substr($text, 0, 500);

            $result = $this->llm->chat([
                ['role' => 'user', 'content' => $prompt],
            ], ['temperature' => 0.3, 'max_tokens' => 100]);

            $reply = $result['content'] ?? '';
            $tags = explode(',', $reply);
            return array_map('trim', array_filter($tags, fn($t) => mb_strlen(trim($t)) >= 2));
        } catch (\Throwable $e) {
            Log::warning('[TagSuggestion] AI 标签生成失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 加载热门标签
     */
    protected function loadHotTags(): void
    {
        if ($this->hotTags === null) {
            $this->hotTags = ForumTag::withCount('posts')
                ->orderBy('posts_count', 'desc')
                ->limit(100)
                ->get(['id', 'name', 'slug'])
                ->toArray();
        }
    }
}
