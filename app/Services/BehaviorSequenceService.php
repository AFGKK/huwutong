<?php

namespace App\Services;

use App\Models\OaArticle;
use App\Models\OaArticleRead;
use App\Models\Like;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\License;
use Illuminate\Support\Facades\DB;

/**
 * 用户行为序列模型 (Markov Chain)
 * 
 * 追踪用户的行为序列并基于马尔可夫链预测下一个最可能感兴趣的文章。
 * 
 * 一阶马尔可夫: P(next | current) — 根据当前文章预测下一篇
 * 二阶马尔可夫: P(next | prev1, prev2) — 根据前两篇文章预测下一篇
 * 加权融合: 0.7 × 一阶 + 0.3 × 二阶
 */
class BehaviorSequenceService
{
    const SESSION_TIMEOUT = 30; // 30分钟无操作视为新会话
    const SESSION_MAX_GAP = 30; // 分钟

    /**
     * 记录用户行为到序列
     */
    public function record(int $userId, int $articleId, string $action): void
    {
        $sessionId = $this->getOrCreateSessionId($userId, $articleId);
        $position = $this->getNextPosition($userId, $sessionId);

        DB::table('oa_behavior_sequences')->insert([
            'user_id'    => $userId,
            'article_id' => $articleId,
            'action'     => $action,
            'session_id' => $sessionId,
            'position'   => $position,
            'created_at' => now(),
        ]);
    }

    /**
     * 获取或创建会话ID
     */
    private function getOrCreateSessionId(int $userId, int $articleId): string
    {
        // 查找用户最近的会话（30分钟内）
        $lastSeq = DB::table('oa_behavior_sequences')
            ->where('user_id', $userId)
            ->latest('created_at')
            ->first();

        if ($lastSeq) {
            $gapMinutes = now()->diffInMinutes($lastSeq->created_at);
            if ($gapMinutes <= self::SESSION_MAX_GAP) {
                return $lastSeq->session_id;
            }
        }

        return uniqid("sess_{$userId}_", true);
    }

    private function getNextPosition(int $userId, string $sessionId): int
    {
        $lastPos = DB::table('oa_behavior_sequences')
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->max('position');
        return ($lastPos ?? 0) + 1;
    }

    /**
     * 批量构建马尔可夫转移矩阵
     */
    public function buildTransitionMatrix(): array
    {
        // 一阶转移：从某个文章到下一个文章
        $this->buildFirstOrderTransitions();
        // 二阶转移：从两个连续文章到下一个
        $this->buildSecondOrderTransitions();

        return [
            'first_order'  => DB::table('oa_markov_transitions')->count(),
            'second_order' => DB::table('oa_markov_transitions_v2')->count(),
        ];
    }

    private function buildFirstOrderTransitions(): void
    {
        // 清空重建
        DB::table('oa_markov_transitions')->truncate();

        // 从序列中提取相邻文章对 (read & like 行为)
        $pairs = DB::select("
            SELECT 
                a1.article_id AS from_id,
                a2.article_id AS to_id,
                COUNT(*) AS cnt
            FROM oa_behavior_sequences a1
            JOIN oa_behavior_sequences a2 
                ON a1.user_id = a2.user_id 
                AND a1.session_id = a2.session_id
                AND a2.position = a1.position + 1
            WHERE a1.action IN ('read', 'like')
              AND a2.action IN ('read', 'like')
            GROUP BY a1.article_id, a2.article_id
        ");

        if (empty($pairs)) return;

        // 计算总转移次数（用于概率归一化）
        $fromTotals = [];
        foreach ($pairs as $p) {
            $fromTotals[$p->from_id] = ($fromTotals[$p->from_id] ?? 0) + $p->cnt;
        }

        $batch = [];
        foreach ($pairs as $p) {
            $total = $fromTotals[$p->from_id] ?? 1;
            $batch[] = [
                'from_article_id' => $p->from_id,
                'to_article_id'   => $p->to_id,
                'count'           => $p->cnt,
                'probability'     => round($p->cnt / $total, 4),
            ];
        }

        foreach (array_chunk($batch, 100) as $chunk) {
            DB::table('oa_markov_transitions')->insert($chunk);
        }
    }

    private function buildSecondOrderTransitions(): void
    {
        DB::table('oa_markov_transitions_v2')->truncate();

        $triples = DB::select("
            SELECT 
                a1.article_id AS from1_id,
                a2.article_id AS from2_id,
                a3.article_id AS to_id,
                COUNT(*) AS cnt
            FROM oa_behavior_sequences a1
            JOIN oa_behavior_sequences a2 
                ON a1.user_id = a2.user_id 
                AND a1.session_id = a2.session_id
                AND a2.position = a1.position + 1
            JOIN oa_behavior_sequences a3
                ON a2.user_id = a3.user_id
                AND a2.session_id = a3.session_id
                AND a3.position = a2.position + 1
            WHERE a1.action IN ('read', 'like')
              AND a2.action IN ('read', 'like')
              AND a3.action IN ('read', 'like')
            GROUP BY a1.article_id, a2.article_id, a3.article_id
        ");

        if (empty($triples)) return;

        $fromTotals = [];
        foreach ($triples as $t) {
            $key = $t->from1_id . '_' . $t->from2_id;
            $fromTotals[$key] = ($fromTotals[$key] ?? 0) + $t->cnt;
        }

        $batch = [];
        foreach ($triples as $t) {
            $key = $t->from1_id . '_' . $t->from2_id;
            $total = $fromTotals[$key] ?? 1;
            $batch[] = [
                'from_article_id_1' => $t->from1_id,
                'from_article_id_2' => $t->from2_id,
                'to_article_id'     => $t->to_id,
                'count'             => $t->cnt,
                'probability'       => round($t->cnt / $total, 4),
            ];
        }

        foreach (array_chunk($batch, 100) as $chunk) {
            DB::table('oa_markov_transitions_v2')->insert($chunk);
        }
    }

    /**
     * ⭐ 基于马尔可夫链预测下一篇文章
     * 
     * 一阶预测: 根据用户最后阅读的文章
     * 二阶预测: 根据用户最后两个文章
     * 加权融合: 0.7 × 一阶 + 0.3 × 二阶
     * 
     * @return array [article_id => score]
     */
    public function predictNext(int $userId, int $topN = 30): array
    {
        // 获取用户最近的序列（最后2个文章）
        $lastArticles = DB::table('oa_behavior_sequences')
            ->where('user_id', $userId)
            ->whereIn('action', ['read', 'like'])
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->pluck('article_id')
            ->toArray();

        if (empty($lastArticles)) return [];

        $scores = [];

        // 一阶马尔可夫预测
        $firstOrder = DB::table('oa_markov_transitions')
            ->where('from_article_id', $lastArticles[0])
            ->where('probability', '>', 0.01)
            ->orderBy('probability', 'desc')
            ->take(20)
            ->get();

        foreach ($firstOrder as $t) {
            $scores[$t->to_article_id] = ($scores[$t->to_article_id] ?? 0) + $t->probability * 0.7;
        }

        // 二阶马尔可夫预测（如果有2个文章记录）
        if (count($lastArticles) >= 2) {
            $secondOrder = DB::table('oa_markov_transitions_v2')
                ->where('from_article_id_1', $lastArticles[1])
                ->where('from_article_id_2', $lastArticles[0])
                ->where('probability', '>', 0.01)
                ->orderBy('probability', 'desc')
                ->take(15)
                ->get();

            foreach ($secondOrder as $t) {
                $scores[$t->to_article_id] = ($scores[$t->to_article_id] ?? 0) + $t->probability * 0.3;
            }
        }

        // 排除已看过的
        $seenIds = $this->getUserSeenArticleIds($userId);
        foreach ($seenIds as $sid) {
            unset($scores[$sid]);
        }

        arsort($scores);
        return array_slice($scores, 0, $topN, true);
    }

    /**
     * 获取用户在当前会话中最近看的文章序列
     */
    public function getCurrentSessionSequence(int $userId): array
    {
        $lastSeq = DB::table('oa_behavior_sequences')
            ->where('user_id', $userId)
            ->latest('created_at')
            ->first();

        if (!$lastSeq) return [];

        $gapMinutes = now()->diffInMinutes($lastSeq->created_at);
        if ($gapMinutes > self::SESSION_MAX_GAP) return [];

        return DB::table('oa_behavior_sequences')
            ->where('user_id', $userId)
            ->where('session_id', $lastSeq->session_id)
            ->whereIn('action', ['read', 'like'])
            ->orderBy('position')
            ->pluck('article_id')
            ->toArray();
    }

    private function getUserSeenArticleIds(int $userId): array
    {
        return DB::table('oa_behavior_sequences')
            ->where('user_id', $userId)
            ->whereIn('action', ['read', 'like'])
            ->distinct('article_id')
            ->pluck('article_id')
            ->toArray();
    }

    // ══════════════════════════════════════════
    // ╎  产品序列预测（基于购买顺序）
    // ══════════════════════════════════════════

    /**
     * 基于购买顺序预测用户下一个可能购买的产品
     * 使用马尔可夫链原理：买了产品A的人下一步买了什么
     * @return array [product_id => score]
     */
    public function predictNextProduct(int $userId, int $limit = 20): array
    {
        // 获取用户购买过的产品ID（按购买时间排序）
        $purchasedIds = License::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->whereNotNull('product_id')
            ->orderBy('created_at')
            ->pluck('product_id')
            ->toArray();

        if (count($purchasedIds) < 2) return [];

        $lastProduct = end($purchasedIds);
        $prevProduct = count($purchasedIds) >= 2 ? $purchasedIds[count($purchasedIds) - 2] : null;

        $scores = [];

        // 一阶：买了这个产品的人，下一步买了什么
        $firstOrder = DB::table('licenses as l1')
            ->join('licenses as l2', function($j) {
                $j->on('l1.customer_id', '=', 'l2.customer_id')
                  ->where('l1.product_id', '!=', DB::raw('l2.product_id'))
                  ->whereColumn('l2.created_at', '>', 'l1.created_at');
            })
            ->join('customers', 'l1.customer_id', '=', 'customers.id')
            ->where('l1.product_id', $lastProduct)
            ->selectRaw('l2.product_id, COUNT(DISTINCT l1.customer_id) as co_count')
            ->groupBy('l2.product_id')
            ->orderBy('co_count', 'desc')
            ->limit(15)
            ->pluck('co_count', 'product_id')
            ->toArray();

        foreach ($firstOrder as $pid => $cnt) {
            $scores[$pid] = ($scores[$pid] ?? 0) + $cnt * 0.7;
        }

        // 二阶：买了前两个产品的人，下一步买了什么
        if ($prevProduct) {
            $secondOrder = DB::table('licenses as l1')
                ->join('licenses as l2', function($j) {
                    $j->on('l1.customer_id', '=', 'l2.customer_id')
                      ->where('l1.product_id', '!=', DB::raw('l2.product_id'))
                      ->whereColumn('l2.created_at', '>', 'l1.created_at');
                })
                ->join('licenses as l3', function($j) {
                    $j->on('l2.customer_id', '=', 'l3.customer_id')
                      ->where('l3.product_id', '!=', DB::raw('l2.product_id'))
                      ->whereColumn('l3.created_at', '>', 'l2.created_at');
                })
                ->join('customers', 'l1.customer_id', '=', 'customers.id')
                ->where('l1.product_id', $prevProduct)
                ->where('l2.product_id', $lastProduct)
                ->selectRaw('l3.product_id, COUNT(DISTINCT l1.customer_id) as co_count')
                ->groupBy('l3.product_id')
                ->orderBy('co_count', 'desc')
                ->limit(10)
                ->pluck('co_count', 'product_id')
                ->toArray();

            foreach ($secondOrder as $pid => $cnt) {
                $scores[$pid] = ($scores[$pid] ?? 0) + $cnt * 0.3;
            }
        }

        // 排除已购买的产品
        foreach ($purchasedIds as $pid) {
            unset($scores[$pid]);
        }

        arsort($scores);
        return array_slice($scores, 0, $limit, true);
    }
}
