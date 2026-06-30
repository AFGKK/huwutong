<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\ConversationMessage;
use App\Models\RagDocument;
use App\Services\LlmService;
use App\Services\KnowledgeBaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI 搜索增强引擎
 *
 * 在原有 RAG 引擎基础上增加：
 * 1. LLM Embedding（替代简单 TF-IDF）
 * 2. 混合搜索（向量 + 关键词）
 * 3. LLM 重排序（Cross-encoder 风格）
 * 4. 多内容源统一搜索
 */
class VectorSearchService
{
    protected LlmService $llm;
    protected KnowledgeBaseService $kb;
    protected RagEngineService $rag;

    public function __construct(LlmService $llm, KnowledgeBaseService $kb, RagEngineService $rag)
    {
        $this->llm = $llm;
        $this->kb = $kb;
        $this->rag = $rag;
    }

    // ═══════════════════════════════════════════
    //  统一搜索入口
    // ═══════════════════════════════════════════

    /**
     * 统一搜索（混合模式）
     *
     * @param string $query 搜索查询
     * @param array $options {types: string[], limit: int, hybrid: bool}
     * @return array{results: array, total: int, query: string, mode: string}
     */
    public function search(string $query, array $options = []): array
    {
        $types = $options['types'] ?? ['kb', 'conversation', 'rag'];
        $limit = $options['limit'] ?? 10;
        $hybrid = $options['hybrid'] ?? true;

        $allResults = [];

        // 来源1: KB 文章
        if (in_array('kb', $types)) {
            try {
                $kbResults = $this->searchKb($query, $limit);
                foreach ($kbResults as $r) {
                    $allResults[] = $r;
                }
            } catch (\Throwable $e) {
                Log::warning("[VectorSearch] KB搜索失败: " . $e->getMessage());
            }
        }

        // 来源2: RAG 文档（向量搜索）
        if (in_array('rag', $types)) {
            try {
                $ragResults = $this->searchRag($query, $limit, $hybrid);
                foreach ($ragResults as $r) {
                    $allResults[] = $r;
                }
            } catch (\Throwable $e) {
                Log::warning("[VectorSearch] RAG搜索失败: " . $e->getMessage());
            }
        }

        // 来源3: 对话消息
        if (in_array('conversation', $types)) {
            try {
                $convResults = $this->searchConversations($query, $limit);
                foreach ($convResults as $r) {
                    $allResults[] = $r;
                }
            } catch (\Throwable $e) {
                Log::warning("[VectorSearch] 对话搜索失败: " . $e->getMessage());
            }
        }

        // 去重（按内容哈希）
        $seen = [];
        $unique = [];
        foreach ($allResults as $r) {
            $key = md5(mb_substr($r['content'] ?? '', 0, 100));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $r;
            }
        }

        // LLM 重排序（如果结果较多）
        if (count($unique) > 3) {
            $unique = $this->rerank($query, $unique, $limit);
        } else {
            usort($unique, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
            $unique = array_slice($unique, 0, $limit);
        }

        return [
            'results' => $unique,
            'total' => count($unique),
            'query' => $query,
            'mode' => $hybrid ? 'hybrid' : 'vector',
        ];
    }

    // ═══════════════════════════════════════════
    //  单源搜索
    // ═══════════════════════════════════════════

    /**
     * KB 文章搜索（关键词 + 全文索引）
     */
    protected function searchKb(string $query, int $limit): array
    {
        $articles = KbArticle::published()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->orderBy('helpful_count', 'desc')
            ->take($limit)
            ->get(['id', 'title', 'content', 'excerpt', 'category_id', 'helpful_count']);

        $results = [];
        foreach ($articles as $a) {
            // 简单打分：标题匹配加分
            $score = 0.5;
            if (mb_stripos($a->title, $query) !== false) $score += 0.3;

            $results[] = [
                'id' => "kb_{$a->id}",
                'source_type' => 'kb_article',
                'source_id' => $a->id,
                'title' => $a->title,
                'content' => mb_substr($a->content, 0, 500),
                'excerpt' => $a->excerpt,
                'score' => round($score, 4),
                'url' => null,
            ];
        }

        return $results;
    }

    /**
     * RAG 文档向量搜索（支持混合模式）
     */
    protected function searchRag(string $query, int $limit, bool $hybrid): array
    {
        // 生成 LLM Embedding
        $queryEmbedding = $this->generateLLMEmbedding($query);

        // 获取所有 RAG 文档
        $documents = RagDocument::get(['id', 'title', 'content', 'embedding', 'source_type', 'source_id']);

        $scored = [];
        foreach ($documents as $doc) {
            if (empty($doc->embedding)) continue;

            $docEmbedding = $doc->embedding;
            // 如果是旧格式（关联数组），转为值数组
            if (is_array($docEmbedding) && !isset($docEmbedding[0])) {
                $docEmbedding = array_values($docEmbedding);
            }

            // 向量相似度
            $vectorScore = $this->cosineSimilarity($queryEmbedding, $docEmbedding);

            // 关键词匹配加分（混合模式）
            $keywordBonus = 0;
            if ($hybrid) {
                $title = $doc->title ?? '';
                $content = $doc->content ?? '';
                if (mb_stripos($title, $query) !== false) $keywordBonus += 0.2;
                if (mb_stripos($content, $query) !== false) $keywordBonus += 0.1;
            }

            $finalScore = $vectorScore + $keywordBonus;

            if ($finalScore > 0.1) {
                $scored[] = [
                    'id' => "rag_{$doc->id}",
                    'source_type' => $doc->source_type ?? 'rag',
                    'source_id' => $doc->source_id,
                    'title' => $doc->title ?? '',
                    'content' => mb_substr($doc->content ?? '', 0, 500),
                    'excerpt' => null,
                    'score' => round($finalScore, 4),
                    'url' => null,
                ];
            }
        }

        // 按分数降序
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    /**
     * 对话消息全文搜索
     */
    protected function searchConversations(string $query, int $limit): array
    {
        $messages = ConversationMessage::where('message_type', 'text')
            ->whereNotNull('content')
            ->where('content', 'like', "%{$query}%")
            ->with('conversation:id,type,name')
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get(['id', 'conversation_id', 'content', 'sender_id', 'created_at']);

        $results = [];
        foreach ($messages as $m) {
            $convName = $m->conversation?->name ?? '对话';

            $results[] = [
                'id' => "msg_{$m->id}",
                'source_type' => 'conversation_message',
                'source_id' => $m->id,
                'title' => "消息 #{$m->id} - {$convName}",
                'content' => mb_substr($m->content, 0, 500),
                'excerpt' => null,
                'score' => 0.6,
                'url' => null,
                'created_at' => $m->created_at->toDateTimeString(),
            ];
        }

        return $results;
    }

    // ═══════════════════════════════════════════
    //  LLM Embedding
    // ═══════════════════════════════════════════

    /**
     * 使用 LLM 生成语义 Embedding（768 维浮点数向量）
     */
    public function generateLLMEmbedding(string $text): array
    {
        $cacheKey = 'embedding_' . md5($text);

        // 尝试从缓存读取
        $cached = cache()->get($cacheKey);
        if ($cached && is_array($cached)) {
            return $cached;
        }

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => implode("\n", [
                    '你是一个文本向量化引擎。将以下文本转换为语义向量。',
                    '返回一个包含 64 个浮点数的 JSON 数组，每个值在 -1 到 1 之间。',
                    '语义相似的文本应产生相似的向量。',
                    '只返回 JSON 数组，不要其他内容。示例：',
                    '[0.12, -0.34, 0.56, ...]',
                ])],
                ['role' => 'user', 'content' => "文本：{$text}"],
            ], [
                'temperature' => 0.0,
                'max_tokens' => 2000,
                'model' => 'deepseek-chat',
            ], 'vector_embedding');

            $reply = $result['content'] ?? '';

            // 提取 JSON 数组
            if (preg_match('/\[[\s\S]*\]/', $reply, $m)) {
                $embedding = json_decode($m[0], true);
                if (is_array($embedding) && count($embedding) >= 16) {
                    // 限制维度
                    $embedding = array_slice($embedding, 0, 64);
                    // 归一化
                    $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $embedding)));
                    if ($norm > 0) {
                        $embedding = array_map(fn($v) => $v / $norm, $embedding);
                    }
                    // 缓存 1 小时
                    cache()->put($cacheKey, $embedding, 3600);
                    return $embedding;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[VectorSearch] LLM Embedding 失败: " . $e->getMessage());
        }

        // 降级：使用简单词袋向量
        return $this->fallbackEmbedding($text);
    }

    /**
     * 降级 Embedding（词袋模型）
     */
    protected function fallbackEmbedding(string $text): array
    {
        $words = preg_split('/[\s,，。！？、；：()\[\]]+/u', $text);
        $words = array_filter($words, fn($w) => mb_strlen($w) >= 2);
        $freq = array_count_values($words);
        $maxFreq = max($freq) ?: 1;

        $vector = array_fill(0, 64, 0.0);
        $i = 0;
        foreach ($freq as $word => $count) {
            if ($i >= 64) break;
            $vector[$i] = ($count / $maxFreq) * 2 - 1; // 映射到 [-1, 1]
            $i++;
        }

        return $vector;
    }

    /**
     * 余弦相似度（值数组版本）
     */
    protected function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dim = min(count($vecA), count($vecB));
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < $dim; $i++) {
            $a = $vecA[$i] ?? 0;
            $b = $vecB[$i] ?? 0;
            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA * $normB === 0.0) return 0;

        return $dotProduct / ($normA * $normB);
    }

    // ═══════════════════════════════════════════
    //  LLM 重排序
    // ═══════════════════════════════════════════

    /**
     * 使用 LLM 对搜索结果进行重排序（Cross-encoder 风格）
     */
    protected function rerank(string $query, array $results, int $limit): array
    {
        if (empty($results)) return [];

        // 构建排序 prompt
        $items = [];
        foreach ($results as $i => $r) {
            $items[] = "[{$i}] {$r['title']}\n{$r['content']}";
        }

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => implode("\n", [
                    '你是一个搜索排序引擎。根据查询相关性对以下搜索结果排序。',
                    '返回排序后的序号数组，最相关的排在最前。',
                    "查询：{$query}",
                    '只返回 JSON 数组，如 [2, 0, 1, 3]，不要其他内容。',
                ])],
                ['role' => 'user', 'content' => implode("\n\n", $items)],
            ], [
                'temperature' => 0.1,
                'max_tokens' => 500,
                'model' => 'deepseek-chat',
            ], 'search_rerank');

            $reply = $result['content'] ?? '';

            if (preg_match('/\[[\d,\s]+\]/', $reply, $m)) {
                $order = json_decode($m[0], true);
                if (is_array($order) && count($order) > 0) {
                    $sorted = [];
                    foreach ($order as $idx) {
                        if (isset($results[$idx])) {
                            $sorted[] = $results[$idx];
                        }
                    }
                    if (!empty($sorted)) {
                        return array_slice($sorted, 0, $limit);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[VectorSearch] 重排序失败: " . $e->getMessage());
        }

        // 降级：按原分数排序
        usort($results, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        return array_slice($results, 0, $limit);
    }

    // ═══════════════════════════════════════════
    //  索引管理
    // ═══════════════════════════════════════════

    /**
     * 重建所有 RAG 文档的 LLM Embedding
     */
    public function rebuildEmbeddings(bool $force = false): array
    {
        $query = RagDocument::whereNotNull('content');
        if (!$force) {
            // 只重建没有 embedding 的
            $query->where(function ($q) {
                $q->whereNull('embedding')
                  ->orWhere('embedding', '{}');
            });
        }

        $documents = $query->get();
        $updated = 0;

        foreach ($documents as $doc) {
            try {
                $text = ($doc->title ?? '') . "\n" . ($doc->content ?? '');
                if (mb_strlen(trim($text)) < 10) continue;

                $embedding = $this->generateLLMEmbedding(mb_substr($text, 0, 2000));
                $doc->update(['embedding' => $embedding]);
                $updated++;
            } catch (\Throwable $e) {
                Log::error("[VectorSearch] 重建索引失败 doc#{$doc->id}: " . $e->getMessage());
            }
        }

        return ['total' => $documents->count(), 'updated' => $updated];
    }

    /**
     * 获取搜索统计
     */
    public function getStats(): array
    {
        $totalDocs = RagDocument::count();
        $withEmbedding = RagDocument::whereNotNull('embedding')
            ->where('embedding', '!=', '{}')
            ->count();

        return [
            'total_documents' => $totalDocs,
            'indexed_documents' => $withEmbedding,
            'index_coverage' => $totalDocs > 0 ? round($withEmbedding / $totalDocs * 100, 1) : 0,
            'kb_articles' => KbArticle::published()->count(),
        ];
    }
}
