<?php

namespace App\Services;

use App\Models\OaArticle;
use App\Models\OaArticleRead;
use App\Models\OaArticleEmbedding;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\Follow;
use App\Models\OfficialAccount;
use App\Models\Product;
use App\Models\License;
use Illuminate\Support\Facades\DB;

/**
 * AI 推荐引擎
 * 
 * 使用 TF-IDF 加权词向量 + 余弦相似度进行内容推荐。
 * 无需外部 API，完全基于文章内容的词频统计。
 */
class AiRecommendationService
{
    // 嵌入向量维度
    const DIMENSION = 128;
    
    // 哈希种子
    const HASH_SEED = 42;

    /**
     * 为指定文章生成/更新嵌入向量
     */
    public function generateArticleEmbedding(OaArticle $article): array
    {
        // 提取文本内容（标题、摘要、标签）
        $text = $article->title . ' ' . ($article->summary ?? '');
        if (!empty($article->tags)) {
            $tags = is_string($article->tags) ? json_decode($article->tags, true) : $article->tags;
            $text .= ' ' . implode(' ', $tags);
        }

        // 计算TF-IDF加权向量
        $vector = $this->textToVector($text);
        
        // 归一化
        $normalized = $this->normalize($vector);

        // 存储到数据库
        OaArticleEmbedding::updateOrCreate(
            ['article_id' => $article->id],
            ['embedding' => $normalized]
        );

        return $normalized;
    }

    /**
     * 为所有已发布的文章批量生成嵌入
     */
    public function generateAllEmbeddings(): int
    {
        $count = 0;
        OaArticle::where('status', 'published')
            ->chunk(100, function ($articles) use (&$count) {
                foreach ($articles as $article) {
                    try {
                        $this->generateArticleEmbedding($article);
                        $count++;
                    } catch (\Exception $e) {
                        // 跳过错误
                    }
                }
            });
        return $count;
    }

    /**
     * 生成用户兴趣嵌入向量
     * 
     * 根据用户90天内的阅读、点赞、收藏记录，
     * 将交互过的文章嵌入进行加权平均。
     */
    public function generateUserEmbedding(int $userId): array
    {
        // 获取用户交互过的文章ID及权重
        $interactions = [];

        // 阅读记录：权重 1.0
        $readIds = OaArticleRead::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(90))
            ->pluck('article_id')
            ->toArray();
        foreach ($readIds as $aid) {
            $interactions[$aid] = ($interactions[$aid] ?? 0) + 1.0;
        }

        // 点赞记录：权重 2.0
        $likeIds = Like::where('user_id', $userId)
            ->where('likeable_type', OaArticle::class)
            ->where('created_at', '>=', now()->subDays(90))
            ->pluck('likeable_id')
            ->toArray();
        foreach ($likeIds as $aid) {
            $interactions[$aid] = ($interactions[$aid] ?? 0) + 2.0;
        }

        // 收藏记录：权重 1.5
        $favIds = Favorite::where('user_id', $userId)
            ->where('favorable_type', OaArticle::class)
            ->where('created_at', '>=', now()->subDays(90))
            ->pluck('favorable_id')
            ->toArray();
        foreach ($favIds as $aid) {
            $interactions[$aid] = ($interactions[$aid] ?? 0) + 1.5;
        }

        if (empty($interactions)) {
            return []; // 无交互数据，无法生成用户嵌入
        }

        // 获取这些文章的嵌入向量
        $embeddings = OaArticleEmbedding::whereIn('article_id', array_keys($interactions))
            ->get()
            ->keyBy('article_id');

        if ($embeddings->isEmpty()) {
            return [];
        }

        // 加权平均
        $dimension = self::DIMENSION;
        $userVector = array_fill(0, $dimension, 0.0);
        $totalWeight = 0;

        foreach ($interactions as $aid => $weight) {
            $emb = $embeddings->get($aid);
            if (!$emb) continue;
            $vec = is_string($emb->embedding) ? json_decode($emb->embedding, true) : $emb->embedding;
            if (!is_array($vec)) continue;

            foreach ($vec as $i => $val) {
                $userVector[$i] += $val * $weight;
            }
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) return [];

        // 归一化
        foreach ($userVector as $i => $val) {
            $userVector[$i] = $val / $totalWeight;
        }

        return $this->normalize($userVector);
    }

    /**
     * AI 推荐：基于内容嵌入的余弦相似度
     * 
     * @return array [article_id => similarity_score]
     */
    public function recommend(int $userId, int $limit = 30): array
    {
        // 1. 获取用户嵌入
        $userEmbedding = $this->generateUserEmbedding($userId);
        if (empty($userEmbedding)) {
            return [];
        }

        // 2. 获取用户已看过的文章（排除掉）
        $seenIds = OaArticleRead::where('user_id', $userId)
            ->pluck('article_id')
            ->merge(
                Like::where('user_id', $userId)
                    ->where('likeable_type', OaArticle::class)
                    ->pluck('likeable_id')
            )->merge(
                Favorite::where('user_id', $userId)
                    ->where('favorable_type', OaArticle::class)
                    ->pluck('favorable_id')
            )->unique()->values()->toArray();

        // 3. 获取所有已发布文章的嵌入（排除已看过的）
        $query = OaArticleEmbedding::whereHas('article', fn($q) => $q->where('status', 'published'));
        if (!empty($seenIds)) {
            $query->whereNotIn('article_id', $seenIds);
        }

        $articleEmbeddings = $query->get();

        if ($articleEmbeddings->isEmpty()) {
            return [];
        }

        // 4. 计算余弦相似度
        $scores = [];
        foreach ($articleEmbeddings as $ae) {
            $vec = is_string($ae->embedding) ? json_decode($ae->embedding, true) : $ae->embedding;
            if (!is_array($vec)) continue;
            
            $similarity = $this->cosineSimilarity($userEmbedding, $vec);
            if ($similarity > 0) {
                $scores[$ae->article_id] = round($similarity, 4);
            }
        }

        // 5. 按相似度排序
        arsort($scores);

        return array_slice($scores, 0, $limit, true);
    }

    /**
     * 将文本转换为TF-IDF加权向量
     * 使用哈希技巧将词映射到固定维度
     */
    private function textToVector(string $text): array
    {
        $vector = array_fill(0, self::DIMENSION, 0.0);
        
        // 分词
        $words = $this->tokenize($text);
        if (empty($words)) return $vector;

        // 计算词频
        $tf = array_count_values($words);
        $maxTf = max($tf);
        if ($maxTf <= 0) return $vector;

        // 哈希映射到维度 + TF-IDF加权
        foreach ($tf as $word => $count) {
            if (mb_strlen($word) < 2) continue; // 忽略单字
            
            $hash = $this->hashWord($word);
            $dimension = abs($hash) % self::DIMENSION;
            
            // TF (归一化) × IDF (使用对数逆文档频率近似)
            $tfValue = $count / $maxTf;
            $idfValue = log(1 + (1000 / (1 + $this->getDocumentFrequency($word))));
            
            $vector[$dimension] += $tfValue * $idfValue;
        }

        return $vector;
    }

    /**
     * 分词
     */
    private function tokenize(string $text): array
    {
        // 转为小写
        $text = mb_strtolower($text);
        
        // 按非字母数字字符分割
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // 过滤停用词
        $stopWords = ['的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都', '一', '个', '上',
                      '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看', '好', '自己',
                      '这', '他', '她', '它', '们', '那', '些', '什么', '怎么', '如何', '为什么',
                      'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
                      'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
                      'should', 'may', 'might', 'can', 'shall', 'to', 'of', 'in', 'for',
                      'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during',
                      'before', 'after', 'above', 'below', 'between', 'under', 'again',
                      'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why',
                      'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other',
                      'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so',
                      'than', 'too', 'very', 'and', 'but', 'or', 'if', 'because', 'about',
                      'up', 'out', 'just', 'also', 'over'];
        
        return array_filter($words, fn($w) => !in_array($w, $stopWords));
    }

    /**
     * 哈希函数：将词映射到整数
     */
    private function hashWord(string $word): int
    {
        $hash = self::HASH_SEED;
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($word, $i, 1);
            $ord = mb_ord($char);
            $hash = (($hash << 5) - $hash) + $ord;
            $hash = $hash & 0x7FFFFFFF; // 保持为正整数
        }
        return $hash;
    }

    /**
     * 获取词的文档频率（近似）
     * 为简单起见，使用缓存或估算
     */
    private function getDocumentFrequency(string $word): int
    {
        // 简单近似：使用缓存或实时查询
        static $cache = [];
        if (!isset($cache[$word])) {
            try {
                $cache[$word] = DB::table('oa_articles')
                    ->where(function($q) use ($word) {
                        $q->where('title', 'like', "%{$word}%")
                          ->orWhere('summary', 'like', "%{$word}%");
                    })
                    ->count();
            } catch (\Exception $e) {
                $cache[$word] = 1;
            }
        }
        return max($cache[$word], 1);
    }

    /**
     * 计算余弦相似度
     */
    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < self::DIMENSION; $i++) {
            $a = $vecA[$i] ?? 0;
            $b = $vecB[$i] ?? 0;
            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        $denominator = sqrt($normA) * sqrt($normB);
        return $denominator > 0 ? $dotProduct / $denominator : 0;
    }

    /**
     * 向量归一化
     */
    private function normalize(array $vector): array
    {
        $norm = 0;
        foreach ($vector as $val) {
            $norm += $val * $val;
        }
        $norm = sqrt($norm);
        if ($norm <= 0) return $vector;

        foreach ($vector as $i => $val) {
            $vector[$i] = $val / $norm;
        }
        return $vector;
    }

    /**
     * 获取内容相似文章（基于嵌入距离）
     */
    public function getSimilarArticles(int $articleId, int $limit = 6): array
    {
        $embedding = OaArticleEmbedding::where('article_id', $articleId)->first();
        if (!$embedding) return [];

        $targetVec = is_string($embedding->embedding) ? json_decode($embedding->embedding, true) : $embedding->embedding;
        if (!is_array($targetVec)) return [];

        $candidates = OaArticleEmbedding::where('article_id', '!=', $articleId)
            ->whereHas('article', fn($q) => $q->where('status', 'published'))
            ->get();

        $scores = [];
        foreach ($candidates as $cand) {
            $vec = is_string($cand->embedding) ? json_decode($cand->embedding, true) : $cand->embedding;
            if (!is_array($vec)) continue;
            $sim = $this->cosineSimilarity($targetVec, $vec);
            if ($sim > 0) {
                $scores[$cand->article_id] = $sim;
            }
        }

        arsort($scores);
        return array_slice($scores, 0, $limit, true);
    }

    // ══════════════════════════════════════════
    // ╎  产品推荐方法
    // ══════════════════════════════════════════

    /**
     * 生成产品嵌入向量（基于名称+描述+标签）
     */
    public function generateProductEmbedding(Product $product): array
    {
        $text = $product->name . ' ' . ($product->description ?? '');
        if (!empty($product->tags)) {
            $tags = is_string($product->tags) ? json_decode($product->tags, true) : $product->tags;
            $text .= ' ' . implode(' ', $tags);
        }

        $vector = $this->textToVector($text);
        $normalized = $this->normalize($vector);

        DB::table('product_embeddings')->updateOrInsert(
            ['product_id' => $product->id],
            ['embedding' => json_encode($normalized), 'updated_at' => now()]
        );

        return $normalized;
    }

    /**
     * 批量生成所有产品的嵌入
     */
    public function generateAllProductEmbeddings(): int
    {
        $count = 0;
        Product::where('is_active', true)
            ->chunk(100, function ($products) use (&$count) {
                foreach ($products as $product) {
                    try {
                        $this->generateProductEmbedding($product);
                        $count++;
                    } catch (\Exception $e) { /* skip */ }
                }
            });
        return $count;
    }

    /**
     * AI 产品推荐：基于嵌入的余弦相似度
     * @return array [product_id => similarity_score]
     */
    public function recommendProducts(int $userId, int $limit = 30): array
    {
        // 获取用户购买/收藏过的产品
        $purchasedIds = DB::table('licenses')
            ->where('customer_id', function($q) use ($userId) {
                $q->select('id')->from('customers')->where('user_id', $userId)->limit(1);
            })
            ->pluck('product_id')
            ->merge(
                DB::table('wishlists')->where('user_id', $userId)->pluck('product_id')
            )->unique()->values()->toArray();

        if (empty($purchasedIds)) return [];

        // 获取这些产品的嵌入
        $embeddings = DB::table('product_embeddings')
            ->whereIn('product_id', $purchasedIds)
            ->get()
            ->keyBy('product_id');

        if ($embeddings->isEmpty()) return [];

        // 计算用户兴趣向量（加权平均）
        $dimension = self::DIMENSION;
        $userVector = array_fill(0, $dimension, 0.0);
        $count = 0;

        foreach ($embeddings as $emb) {
            $vec = is_string($emb->embedding) ? json_decode($emb->embedding, true) : $emb->embedding;
            if (!is_array($vec)) continue;
            foreach ($vec as $i => $val) {
                $userVector[$i] += $val;
            }
            $count++;
        }

        if ($count <= 0) return [];
        foreach ($userVector as $i => $val) {
            $userVector[$i] = $val / $count;
        }
        $userVector = $this->normalize($userVector);

        // 计算所有产品与用户向量的相似度
        $allProductEmbeddings = DB::table('product_embeddings')
            ->whereNotIn('product_id', $purchasedIds)
            ->get();

        $scores = [];
        foreach ($allProductEmbeddings as $pe) {
            $vec = is_string($pe->embedding) ? json_decode($pe->embedding, true) : $pe->embedding;
            if (!is_array($vec)) continue;
            $sim = $this->cosineSimilarity($userVector, $vec);
            if ($sim > 0) {
                $scores[$pe->product_id] = round($sim, 4);
            }
        }

        arsort($scores);
        return array_slice($scores, 0, $limit, true);
    }

    /**
     * 产品协同过滤：「买了这个的人也买了」
     * @return array [product_id => co_occurrence_score]
     */
    public function productCollaborativeFiltering(int $productId, int $limit = 10): array
    {
        // 找到购买过该产品的客户
        $customerIds = DB::table('licenses')
            ->where('product_id', $productId)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->pluck('customer_id')
            ->toArray();

        if (empty($customerIds)) return [];

        // 这些客户还买了什么其他产品
        $scores = DB::table('licenses')
            ->whereIn('customer_id', $customerIds)
            ->where('product_id', '!=', $productId)
            ->selectRaw('product_id, COUNT(DISTINCT customer_id) as co_count')
            ->groupBy('product_id')
            ->orderBy('co_count', 'desc')
            ->limit($limit)
            ->pluck('co_count', 'product_id')
            ->toArray();

        return $scores;
    }
}
