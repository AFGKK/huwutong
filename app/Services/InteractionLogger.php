<?php

namespace App\Services;

use App\Models\AiInteractionLog;

/**
 * AI 交互日志记录器
 *
 * 记录每次 AI 调用的上下文、性能指标和结果质量，
 * 为自学习引擎提供数据基础。
 */
class InteractionLogger
{
    /**
     * 记录一次 AI 交互
     */
    public function log(array $data): AiInteractionLog
    {
        return AiInteractionLog::create(array_merge([
            'session_id' => $data['session_id'] ?? null,
            'source_type' => $data['source_type'] ?? 'unknown',
            'source_id' => $data['source_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'prompt' => mb_substr($data['prompt'] ?? '', 0, 5000),
            'response' => mb_substr($data['response'] ?? '', 0, 10000),
            'model' => $data['model'] ?? null,
            'provider' => $data['provider'] ?? null,
            'temperature' => $data['temperature'] ?? null,
            'prompt_tokens' => $data['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['completion_tokens'] ?? 0,
            'total_tokens' => $data['total_tokens'] ?? 0,
            'response_time_ms' => $data['response_time_ms'] ?? 0,
            'status' => $data['status'] ?? 'success',
            'metadata' => $data['metadata'] ?? null,
        ]));
    }

    /**
     * 标记交互质量
     */
    public function markQuality(int $logId, float $score, ?bool $hallucination = null): void
    {
        $update = ['quality_score' => $score];
        if ($hallucination !== null) {
            $update['had_hallucination'] = $hallucination;
        }
        AiInteractionLog::where('id', $logId)->update($update);
    }

    /**
     * 标记用户反馈
     */
    public function markHelpful(int $logId, bool $helpful): void
    {
        AiInteractionLog::where('id', $logId)->update(['was_helpful' => $helpful]);
    }

    /**
     * 获取统计
     */
    public function getStats(): array
    {
        $total = AiInteractionLog::count();
        $recent = AiInteractionLog::where('created_at', '>=', now()->subDay())->count();

        return [
            'total_interactions' => $total,
            'last_24h' => $recent,
            'avg_tokens' => round(AiInteractionLog::avg('total_tokens') ?? 0),
            'avg_response_time' => round(AiInteractionLog::avg('response_time_ms') ?? 0),
            'helpful_rate' => $total > 0
                ? round(AiInteractionLog::where('was_helpful', true)->count() / $total * 100, 1)
                : 0,
            'hallucination_rate' => $total > 0
                ? round(AiInteractionLog::where('had_hallucination', true)->count() / $total * 100, 1)
                : 0,
            'by_source' => AiInteractionLog::selectRaw('source_type, count(*) as total')
                ->groupBy('source_type')->pluck('total', 'source_type')->toArray(),
        ];
    }
}
