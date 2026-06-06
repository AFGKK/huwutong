<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\RagConversation;
use App\Models\RagDocument;
use App\Models\RagMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * RAG 知识引擎
 *
 * 实现知识检索增强生成（Retrieval-Augmented Generation）
 * 支持：KB 文章向量化、语义检索、重排序、置信度过滤、来源引用
 */
class RagEngineService
{
    /**
     * 最小置信度阈值
     */
    private const MIN_CONFIDENCE = 0.35;

    /**
     * 返回的最大文档数
     */
    private const MAX_RESULTS = 5;

    /**
     * 单文档最大字符数
     */
    private const CHUNK_SIZE = 1000;

    /**
     * 索引 KB 文章到 RAG 文档库
     */
    public function indexArticle(KbArticle $article): void
    {
        // 清除旧的索引
        RagDocument::bySource('kb_article', $article->id)->delete();

        // 将文章分块
        $chunks = $article->getChunks(self::CHUNK_SIZE);

        foreach ($chunks as $chunk) {
            // 生成简单的 TF-IDF 风格向量（使用词频统计作为 embedding 替代）
            $simpleEmbedding = $this->generateSimpleEmbedding($chunk['text']);

            RagDocument::create([
                'source_type' => 'kb_article',
                'source_id' => $article->id,
                'title' => $article->title,
                'content' => $chunk['text'],
                'embedding' => $simpleEmbedding,
                'metadata' => [
                    'chunk_index' => $chunk['chunk_index'],
                    'category_id' => $article->category_id,
                    'tags' => $article->tags,
                    'article_slug' => $article->slug,
                ],
                'locale' => $article->locale,
            ]);
        }

        Log::info('RAG: indexed article', [
            'article_id' => $article->id,
            'chunks' => count($chunks),
        ]);
    }

    /**
     * 移除文章索引
     */
    public function removeArticleIndex(KbArticle $article): void
    {
        RagDocument::bySource('kb_article', $article->id)->delete();
    }

    /**
     * 重建所有 KB 文章的索引
     */
    public function rebuildIndex(): array
    {
        $count = 0;
        KbArticle::published()->chunk(50, function ($articles) use (&$count) {
            foreach ($articles as $article) {
                try {
                    $this->indexArticle($article);
                    $count++;
                } catch (\Throwable $e) {
                    Log::error('RAG: index failed for article', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('RAG: index rebuilt', ['articles_indexed' => $count]);
        return ['indexed' => $count];
    }

    /**
     * 检索相关文档（语义搜索，带重排序和置信度过滤）
     */
    public function retrieve(string $query, array $options = []): array
    {
        $queryEmbedding = $this->generateSimpleEmbedding($query);
        $minConfidence = $options['min_confidence'] ?? self::MIN_CONFIDENCE;
        $maxResults = $options['max_results'] ?? self::MAX_RESULTS;
        $locale = $options['locale'] ?? 'zh-CN';

        // 1. 从数据库检索所有文档（实际生产环境应使用向量数据库）
        $documents = RagDocument::where('locale', $locale)
            ->get(['id', 'title', 'content', 'embedding', 'metadata', 'source_type', 'source_id']);

        // 2. 计算余弦相似度
        $scored = [];
        foreach ($documents as $doc) {
            if (empty($doc->embedding)) continue;

            $similarity = $this->cosineSimilarity($queryEmbedding, $doc->embedding);
            $boostedSimilarity = $this->applyBoosts($similarity, $query, $doc);

            if ($boostedSimilarity >= $minConfidence) {
                $scored[] = [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'content' => Str::limit($doc->content, 500),
                    'source_type' => $doc->source_type,
                    'source_id' => $doc->source_id,
                    'metadata' => $doc->metadata,
                    'score' => round($boostedSimilarity, 4),
                ];
            }
        }

        // 3. 按分数降序排列
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // 4. 取 Top-K
        $results = array_slice($scored, 0, $maxResults);

        // 5. 通过关键词匹配进行重排序
        $results = $this->rerank($query, $results);

        return [
            'results' => $results,
            'total_found' => count($scored),
            'query' => $query,
            'confidence_threshold' => $minConfidence,
        ];
    }

    /**
     * 生成答案（检索 + LLM 生成）
     */
    public function answer(string $query, string $sessionId = null, array $options = []): array
    {
        $startTime = microtime(true);

        // 1. 检索相关文档
        $retrievalResult = $this->retrieve($query, $options);
        $documents = $retrievalResult['results'];

        // 2. 获取或创建对话
        $conversation = $this->getOrCreateConversation($sessionId, $options['user_id'] ?? null);

        // 3. 提取上下文
        $context = $this->buildContext($documents);

        // 4. 生成回答
        $response = $this->generateResponse($query, $context, $documents, $options);

        $responseTime = (microtime(true) - $startTime) * 1000;

        // 5. 保存到对话记录
        $this->saveMessages($conversation, $query, $response, $documents, $responseTime);

        return [
            'conversation_id' => $conversation->id,
            'session_id' => $sessionId,
            'answer' => $response['answer'],
            'sources' => array_map(fn($d) => [
                'title' => $d['title'],
                'content' => $d['content'],
                'score' => $d['score'],
            ], $documents),
            'confidence' => $response['confidence'],
            'response_time_ms' => round($responseTime, 2),
            'total_documents_found' => $retrievalResult['total_found'],
        ];
    }

    /**
     * 获取对话历史
     */
    public function getConversationHistory(string $sessionId, int $limit = 20): array
    {
        $conversation = RagConversation::where('session_id', $sessionId)->first();
        if (!$conversation) {
            return [];
        }

        return RagMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 记录满意度反馈
     */
    public function recordFeedback(int $messageId, bool $wasHelpful): void
    {
        RagMessage::where('id', $messageId)->update(['was_helpful' => $wasHelpful]);
    }

    /**
     * 获取 RAG 统计
     */
    public function getStats(): array
    {
        return [
            'total_documents' => RagDocument::count(),
            'total_conversations' => RagConversation::count(),
            'total_messages' => RagMessage::count(),
            'documents_by_source' => RagDocument::select('source_type')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('source_type')
                ->pluck('count', 'source_type')
                ->toArray(),
        ];
    }

    /**
     * 生成简单向量（基于词频的 TF-IDF 风格表示）
     * 生产环境应使用 embedding API（如 text-embedding-ada-002）
     */
    protected function generateSimpleEmbedding(string $text): array
    {
        // 分词（按空格和中英文分割）
        $words = preg_split('/[\s,，。！？、；：()\[\]]+/u', $text);
        $words = array_filter($words, fn($w) => mb_strlen($w) >= 2);

        // 统计词频
        $freq = array_count_values($words);

        // 归一化
        $maxFreq = max($freq) ?: 1;
        $vector = [];
        foreach ($freq as $word => $count) {
            // 取前 100 个高频词作为特征
            if (count($vector) >= 100) break;
            $vector[md5($word)] = $count / $maxFreq;
        }

        return $vector;
    }

    /**
     * 计算余弦相似度
     */
    protected function cosineSimilarity(array $vecA, array $vecB): float
    {
        $intersection = array_intersect_key($vecA, $vecB);
        if (empty($intersection)) {
            return 0;
        }

        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        foreach ($intersection as $key => $value) {
            $dotProduct += $value * ($vecB[$key] ?? 0);
        }

        foreach ($vecA as $value) {
            $normA += $value * $value;
        }
        foreach ($vecB as $value) {
            $normB += $value * $value;
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA * $normB === 0.0) {
            return 0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * 应用提升因子（标题匹配、标签匹配等）
     */
    protected function applyBoosts(float $similarity, string $query, RagDocument $doc): float
    {
        $boost = 1.0;

        // 标题匹配提升
        $queryWords = preg_split('/[\s,，。！？、；：()\[\]]+/u', $query);
        foreach ($queryWords as $word) {
            if (mb_strlen($word) >= 2 && mb_stripos($doc->title, $word) !== false) {
                $boost += 0.15;
            }
        }

        // 标签匹配提升
        $tags = $doc->metadata['tags'] ?? [];
        foreach ($tags as $tag) {
            foreach ($queryWords as $word) {
                if (mb_strlen($word) >= 2 && mb_stripos($tag, $word) !== false) {
                    $boost += 0.1;
                }
            }
        }

        return $similarity * min($boost, 2.0); // 最大提升 2x
    }

    /**
     * 重排序
     */
    protected function rerank(string $query, array $results): array
    {
        if (empty($results)) return $results;

        // 关键词精确匹配加权重排
        $queryWords = preg_split('/[\s,，。！？、；：()\[\]]+/u', $query);

        foreach ($results as &$result) {
            $exactMatchBonus = 0;
            foreach ($queryWords as $word) {
                if (mb_strlen($word) < 2) continue;
                // 内容中精确匹配加权重
                $count = mb_substr_count($result['content'], $word);
                $exactMatchBonus += $count * 0.02;
            }
            $result['score'] = min($result['score'] + $exactMatchBonus, 1.0);
        }
        unset($result);

        // 重新排序
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }

    /**
     * 构建 LLM 上下文
     */
    protected function buildContext(array $documents): string
    {
        if (empty($documents)) {
            return '未找到相关文档。';
        }

        $context = '';
        foreach ($documents as $i => $doc) {
            $context .= "[文档 {$i}]\n标题: {$doc['title']}\n内容: {$doc['content']}\n\n";
        }

        return $context;
    }

    /**
     * 生成回答（使用 LLM 或本地规则）
     */
    protected function generateResponse(string $query, string $context, array $documents, array $options): array
    {
        try {
            $llmService = app(LlmService::class);

            $systemPrompt = "你是一个互物通授权管理系统的智能客服助手。你的职责是基于知识库文档回答用户关于授权、激活、设备管理、计费等问题。\n\n" .
                "规则：\n" .
                "1. 优先使用提供的文档内容回答问题\n" .
                "2. 如果文档不足以回答，诚实告知用户并建议联系技术支持\n" .
                "3. 回答要简洁、准确\n" .
                "4. 在回答末尾列出参考文档标题\n\n" .
                "参考文档：\n{$context}";

            $response = $llmService->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $query],
            ], ['no_fallback' => true]);

            $answer = $response['choices'][0]['message']['content'] ?? '抱歉，我暂时无法回答这个问题。';

            // 如果没有找到相关文档，降低置信度
            $confidence = empty($documents) ? 0.2 : 0.85;

            return [
                'answer' => $answer,
                'confidence' => $confidence,
                'token_count' => $response['usage']['total_tokens'] ?? 0,
            ];
        } catch (\Throwable $e) {
            // LLM 不可用时使用本地规则回答
            Log::debug('RAG: LLM unavailable, using local fallback', ['error' => $e->getMessage()]);
            return $this->localFallbackResponse($query, $documents);
        }
    }

    /**
     * 本地规则回答（当 LLM 不可用时）
     */
    protected function localFallbackResponse(string $query, array $documents): array
    {
        if (empty($documents)) {
            return [
                'answer' => '关于您的问题，我目前的知识库中没有找到相关信息。请尝试换一种提问方式，或联系技术支持获取帮助。',
                'confidence' => 0.2,
                'token_count' => 0,
            ];
        }

        // 取最相关的文档作为回答
        $topDoc = $documents[0];
        $answer = "根据知识库中的文章「{$topDoc['title']}」，相关内容如下：\n\n{$topDoc['content']}";

        if (count($documents) > 1) {
            $answer .= "\n\n更多相关文章：\n";
            foreach (array_slice($documents, 1, 3) as $doc) {
                $answer .= "- {$doc['title']}\n";
            }
        }

        return [
            'answer' => $answer,
            'confidence' => $topDoc['score'],
            'token_count' => 0,
        ];
    }

    /**
     * 获取或创建对话
     */
    protected function getOrCreateConversation(?string $sessionId, ?int $userId): RagConversation
    {
        if ($sessionId) {
            $conversation = RagConversation::where('session_id', $sessionId)->first();
            if ($conversation) {
                return $conversation;
            }
        }

        return RagConversation::create([
            'session_id' => $sessionId ?? Str::uuid()->toString(),
            'user_id' => $userId,
            'locale' => 'zh-CN',
        ]);
    }

    /**
     * 保存对话消息
     */
    protected function saveMessages(
        RagConversation $conversation,
        string $query,
        array $response,
        array $documents,
        float $responseTime
    ): void {
        // 用户消息
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $query,
        ]);

        // 助手回复
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response['answer'],
            'source_documents' => $documents,
            'confidence' => $response['confidence'],
            'token_count' => $response['token_count'] ?? 0,
            'response_time_ms' => $responseTime,
        ]);

        // 更新对话标题（使用用户的第一条消息）
        if ($conversation->messages()->count() <= 2) {
            $conversation->update([
                'title' => Str::limit($query, 100),
            ]);
        }
    }
}
