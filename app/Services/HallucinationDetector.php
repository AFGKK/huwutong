<?php

namespace App\Services;

use App\Models\HallucinationCheck;
use App\Models\KbArticle;
use App\Services\LlmService;
use App\Services\RagEngineService;
use Illuminate\Support\Facades\Log;

/**
 * AI 幻觉检测器
 *
 * 对 AI 生成内容进行事实校验，识别幻觉/矛盾/无法验证的陈述。
 * 三阶段：主张提取 → 事实校验 → 综合评分
 */
class HallucinationDetector
{
    protected LlmService $llm;
    protected RagEngineService $rag;

    public function __construct(LlmService $llm, RagEngineService $rag)
    {
        $this->llm = $llm;
        $this->rag = $rag;
    }

    /**
     * 对一段 AI 生成文本执行幻觉检测
     *
     * @return array{overall_score: float, verdict: string, claims: array, results: array}
     */
    public function inspect(string $text, string $sourceType = 'ai_reply', ?int $sourceId = null): array
    {
        if (mb_strlen(trim($text)) < 20) {
            return $this->emptyResult();
        }

        // ─── Step 1: 提取事实主张 ───
        $claims = $this->extractClaims($text);
        if (empty($claims)) {
            return $this->emptyResult();
        }

        // ─── Step 2: 逐条校验 ───
        $results = [];
        $verifiedCount = 0;
        $unverifiableCount = 0;
        $contradictedCount = 0;

        foreach ($claims as $claim) {
            $checkResult = $this->verifyClaim($claim);
            $results[] = $checkResult;

            switch ($checkResult['status']) {
                case 'verified': $verifiedCount++; break;
                case 'unverified': $unverifiableCount++; break;
                case 'contradicted': $contradictedCount++; break;
            }
        }

        // ─── Step 3: 综合评分 ───
        $total = count($claims);
        $overallScore = $total > 0
            ? round(($verifiedCount + $unverifiableCount * 0.3) / $total, 2)
            : 1.0;

        // 裁决
        $verdict = 'unverified';
        if ($contradictedCount > 0) {
            $verdict = 'contradicted';
        } elseif ($overallScore >= 0.8 && $verifiedCount === $total) {
            $verdict = 'trustworthy';
        } elseif ($overallScore >= 0.5) {
            $verdict = 'pending';
        }

        // ─── 持久化 ───
        $check = HallucinationCheck::create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'original_text' => mb_substr($text, 0, 5000),
            'claims' => $claims,
            'results' => $results,
            'overall_score' => $overallScore,
            'verdict' => $verdict,
            'total_claims' => $total,
            'verified_claims' => $verifiedCount,
            'unverifiable_claims' => $unverifiableCount,
            'contradicted_claims' => $contradictedCount,
        ]);

        return [
            'id' => $check->id,
            'overall_score' => $overallScore,
            'verdict' => $verdict,
            'claims' => $claims,
            'results' => $results,
        ];
    }

    // ═══════════════════════════════════════════
    //  Step 1: 主张提取
    // ═══════════════════════════════════════════

    /**
     * 使用 LLM 从文本中提取可校验的事实主张
     */
    protected function extractClaims(string $text): array
    {
        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => implode("\n", [
                    '从以下文本中提取所有可校验的事实主张。',
                    '只提取有明确对错、可被验证的陈述（数据、日期、定义、事件、关系等）。',
                    '忽略观点、建议、推测性内容。',
                    '返回 JSON 数组：',
                    '[{"claim": "事实陈述原文", "category": "data|definition|event|relation|other"}]',
                    '只返回 JSON，不要其他内容。如果没有可校验的主张，返回 []。',
                ])],
                ['role' => 'user', 'content' => $text],
            ], [
                'temperature' => 0.1,
                'max_tokens' => 2000,
                'model' => 'deepseek-chat',
            ], 'hallucination_extract');

            $reply = $result['content'] ?? '[]';

            if (preg_match('/\[[\s\S]*\]/', $reply, $m)) {
                $claims = json_decode($m[0], true);
                if (is_array($claims)) {
                    return array_map(fn($c) => is_string($c) ? ['claim' => $c, 'category' => 'other'] : $c, $claims);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[Hallucination] 主张提取失败: ' . $e->getMessage());
        }

        // 降级：按标点/换行切分
        $sentences = preg_split('/[。！？\n]+/u', $text);
        $claims = [];
        foreach ($sentences as $s) {
            $s = trim($s);
            if (mb_strlen($s) > 10 && mb_strlen($s) < 200) {
                $claims[] = ['claim' => $s, 'category' => 'other'];
            }
        }

        return array_slice($claims, 0, 10);
    }

    // ═══════════════════════════════════════════
    //  Step 2: 事实校验
    // ═══════════════════════════════════════════

    /**
     * 对单个主张进行多源事实校验
     *
     * @return array{claim: string, status: string, confidence: float, evidence: ?string, source: ?string}
     */
    protected function verifyClaim(array $claim): array
    {
        $claimText = $claim['claim'] ?? '';
        $category = $claim['category'] ?? 'other';

        if (empty($claimText)) {
            return ['claim' => '', 'status' => 'unverified', 'confidence' => 0, 'evidence' => null, 'source' => null];
        }

        $confidence = 0.0;
        $status = 'unverified';
        $evidence = null;
        $source = null;

        // 来源1: RAG 知识库检索
        try {
            $ragResults = $this->rag->retrieve($claimText, ['limit' => 2, 'min_confidence' => 0.2]);
            foreach ($ragResults['results'] ?? [] as $r) {
                if (($r['score'] ?? 0) > 0.5) {
                    $confidence = max($confidence, $r['score']);
                    $evidence = mb_substr($r['content'] ?? '', 0, 300);
                    $source = $r['source'] ?? '知识库';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[Hallucination] RAG检索失败: ' . $e->getMessage());
        }

        // 来源2: KB 文章
        if ($confidence < 0.7) {
            try {
                $articles = KbArticle::published()
                    ->where(function ($q) use ($claimText) {
                        $keywords = array_filter(explode(' ', $claimText), fn($w) => mb_strlen($w) > 2);
                        foreach ($keywords as $kw) {
                            $q->orWhere('title', 'like', "%{$kw}%");
                        }
                    })
                    ->take(3)
                    ->get(['id', 'title', 'content']);

                foreach ($articles as $a) {
                    if (mb_stripos($a->content, $claimText) !== false) {
                        $confidence = max($confidence, 0.8);
                        $evidence = mb_substr($a->content, 0, 300);
                        $source = "帮助中心: {$a->title}";
                        break;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[Hallucination] KB检索失败: ' . $e->getMessage());
            }
        }

        // 来源3: LLM 交叉验证（对关键主张做二次确认）
        if ($confidence < 0.6 && in_array($category, ['data', 'definition', 'event'])) {
            try {
                $crossResult = $this->llm->chat([
                    ['role' => 'system', 'content' => '你是一个事实核查员。判断以下陈述是否可以被验证为真。只返回 JSON：{"can_verify": true/false, "confidence": 0~1, "explanation": "简短原因"}'],
                    ['role' => 'user', 'content' => "陈述：{$claimText}"],
                ], [
                    'temperature' => 0.1,
                    'max_tokens' => 500,
                    'model' => 'deepseek-chat',
                ], 'hallucination_verify');

                $reply = $crossResult['content'] ?? '';
                if (preg_match('/\{.*\}/s', $reply, $m)) {
                    $parsed = json_decode($m[0], true);
                    if ($parsed && isset($parsed['can_verify'])) {
                        if ($parsed['can_verify']) {
                            $llmConfidence = min(0.7, ($parsed['confidence'] ?? 0.5) * 0.8);
                            $confidence = max($confidence, $llmConfidence);
                            $evidence = $parsed['explanation'] ?? 'LLM 交叉验证通过';
                            $source = 'AI 知识验证';
                        } else {
                            // LLM 也无法确认 → 标记为无法验证
                            $source = 'AI 知识验证';
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[Hallucination] LLM验证失败: ' . $e->getMessage());
            }
        }

        // 判定状态
        if ($confidence >= 0.7) {
            $status = 'verified';
        } elseif ($confidence >= 0.3) {
            $status = 'unverified'; // 部分可信但不足
        } else {
            $status = 'unverified';
            // 如果 LLM 交叉验证明确说不能验证
            if ($source === 'AI 知识验证' && $confidence === 0.0) {
                $status = 'unverified';
            }
        }

        return [
            'claim' => $claimText,
            'category' => $category,
            'status' => $status,
            'confidence' => round($confidence, 2),
            'evidence' => $evidence,
            'source' => $source,
        ];
    }

    /**
     * 为 AI 回复内容添加事实校验标记（追加到回复中）
     */
    public function annotate(string $replyContent, string $sourceType = 'ai_reply', ?int $sourceId = null): array
    {
        $check = $this->inspect($replyContent, $sourceType, $sourceId);

        $annotations = [];
        foreach ($check['results'] as $r) {
            if ($r['status'] === 'unverified') {
                $annotations[] = "⚠️ *未经核实的陈述*: {$r['claim']}";
            } elseif ($r['status'] === 'contradicted') {
                $annotations[] = "❌ *与事实矛盾*: {$r['claim']}";
            }
        }

        $suffix = '';
        if (!empty($annotations)) {
            $suffix = "\n\n---\n**📋 事实核查** (可信度: {$check['overall_score']})\n";
            $suffix .= implode("\n", array_slice($annotations, 0, 3));
            if (count($annotations) > 3) {
                $suffix .= "\n...及其他 " . (count($annotations) - 3) . " 条标注";
            }
        }

        return [
            'content' => $replyContent . $suffix,
            'check' => $check,
        ];
    }

    /**
     * 获取检测统计
     */
    public function getStats(): array
    {
        $total = HallucinationCheck::count();
        $byVerdict = HallucinationCheck::selectRaw('verdict, count(*) as total')
            ->groupBy('verdict')
            ->pluck('total', 'verdict')
            ->toArray();

        $avgScore = HallucinationCheck::avg('overall_score');

        return [
            'total_checks' => $total,
            'by_verdict' => $byVerdict,
            'avg_score' => round($avgScore ?? 0, 2),
            'total_claims' => HallucinationCheck::sum('total_claims'),
        ];
    }

    protected function emptyResult(): array
    {
        return [
            'overall_score' => 1.0,
            'verdict' => 'trustworthy',
            'claims' => [],
            'results' => [],
        ];
    }
}
