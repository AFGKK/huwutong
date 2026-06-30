<?php

namespace App\Services;

use App\Models\PricingExperiment;
use App\Models\PricingExperimentParticipant;
use App\Models\PricingExperimentEvent;

/**
 * M3-26 价格实验/A/B定价系统
 */
class PricingExperimentService
{
    /**
     * 获取仪表盘数据
     */
    public function getDashboard(int $tenantId): array
    {
        $total = PricingExperiment::where('tenant_id', $tenantId)->count();
        $running = PricingExperiment::where('tenant_id', $tenantId)->where('status', 'running')->count();
        $completed = PricingExperiment::where('tenant_id', $tenantId)->where('status', 'completed')->count();
        $draft = PricingExperiment::where('tenant_id', $tenantId)->where('status', 'draft')->count();

        $totalParticipants = PricingExperimentParticipant::whereHas('experiment', fn($q) => $q->where('tenant_id', $tenantId))->count();

        $recentExperiments = PricingExperiment::where('tenant_id', $tenantId)
            ->with('creator:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->toArray();

        return compact('total', 'running', 'completed', 'draft', 'totalParticipants', 'recentExperiments');
    }

    /**
     * 分配用户到实验组
     */
    public function assignToExperiment(PricingExperiment $experiment, int $userId, array $context = []): array
    {
        // 检查实验状态
        if ($experiment->status !== 'running') {
            return ['assigned' => false, 'reason' => '实验未在进行中'];
        }

        // 检查分段过滤器
        if (!$this->passesSegmentation($experiment, $context)) {
            return ['assigned' => false, 'reason' => '不符合分群条件'];
        }

        // 检查是否已参与
        $existing = PricingExperimentParticipant::where('experiment_id', $experiment->id)
            ->where('user_id', $userId)->first();

        if ($existing) {
            return ['assigned' => true, 'variant' => $existing->variant, 'existing' => true];
        }

        // 流量分配：基于用户ID哈希确保一致性
        $hash = crc32("{$experiment->id}_{$userId}");
        $variant = ($hash % 100) < $experiment->traffic_split ? 'treatment' : 'control';

        $participant = PricingExperimentParticipant::create([
            'experiment_id' => $experiment->id,
            'user_id' => $userId,
            'variant' => $variant,
            'assigned_at' => now(),
            'context' => $context,
        ]);

        $experiment->increment('sample_size');

        return ['assigned' => true, 'variant' => $variant, 'participant_id' => $participant->id];
    }

    /**
     * 记录实验事件（转化、购买等）
     */
    public function recordEvent(PricingExperiment $experiment, int $userId, string $eventType, array $data = []): ?PricingExperimentEvent
    {
        $participant = PricingExperimentParticipant::where('experiment_id', $experiment->id)
            ->where('user_id', $userId)->first();

        if (!$participant) {
            return null;
        }

        return PricingExperimentEvent::create([
            'experiment_id' => $experiment->id,
            'participant_id' => $participant->id,
            'event_type' => $eventType,
            'variant' => $participant->variant,
            'event_data' => $data,
            'occurred_at' => now(),
        ]);
    }

    /**
     * 计算结果
     */
    public function calculateResults(PricingExperiment $experiment): PricingExperiment
    {
        $controlEvents = PricingExperimentEvent::where('experiment_id', $experiment->id)
            ->where('variant', 'control');

        $treatmentEvents = PricingExperimentEvent::where('experiment_id', $experiment->id)
            ->where('variant', 'treatment');

        $controlCount = PricingExperimentParticipant::where('experiment_id', $experiment->id)
            ->where('variant', 'control')->count();
        $treatmentCount = PricingExperimentParticipant::where('experiment_id', $experiment->id)
            ->where('variant', 'treatment')->count();

        $controlConversions = (clone $controlEvents)->where('event_type', $experiment->target_metric)->count();
        $treatmentConversions = (clone $treatmentEvents)->where('event_type', $experiment->target_metric)->count();

        $controlRate = $controlCount > 0 ? $controlConversions / $controlCount : 0;
        $treatmentRate = $treatmentCount > 0 ? $treatmentConversions / $treatmentCount : 0;
        $improvement = $controlRate > 0 ? (($treatmentRate - $controlRate) / $controlRate) * 100 : 0;

        // 简化 Z-test 计算
        $zScore = $this->calculateZScore($controlRate, $treatmentRate, $controlCount, $treatmentCount);
        $pValue = $this->calculatePValue($zScore);
        $significant = $pValue < (1 - $experiment->confidence_level / 100);

        $results = [
            'control' => [
                'participants' => $controlCount,
                'conversions' => $controlConversions,
                'rate' => round($controlRate * 100, 2) . '%',
            ],
            'treatment' => [
                'participants' => $treatmentCount,
                'conversions' => $treatmentConversions,
                'rate' => round($treatmentRate * 100, 2) . '%',
            ],
            'improvement' => round($improvement, 2) . '%',
            'z_score' => round($zScore, 4),
            'p_value' => round($pValue, 4),
            'statistically_significant' => $significant,
            'calculated_at' => now()->toIso8601String(),
        ];

        $experiment->update(['results' => $results]);
        return $experiment->fresh();
    }

    protected function calculateZScore(float $p1, float $p2, int $n1, int $n2): float
    {
        $p = ($p1 * $n1 + $p2 * $n2) / ($n1 + $n2);
        $se = sqrt($p * (1 - $p) * (1 / $n1 + 1 / $n2));
        return $se > 0 ? ($p2 - $p1) / $se : 0;
    }

    protected function calculatePValue(float $z): float
    {
        // 近似正态分布CDF
        return 2 * (1 - $this->normalCdf(abs($z)));
    }

    protected function normalCdf(float $x): float
    {
        return 0.5 * (1 + erf($x / sqrt(2)));
    }

    protected function passesSegmentation(PricingExperiment $experiment, array $context): bool
    {
        $filters = $experiment->segment_filters ?? [];
        if (empty($filters)) return true;

        foreach ($filters as $key => $values) {
            $contextValue = $context[$key] ?? null;
            if ($contextValue !== null && !in_array($contextValue, (array) $values)) {
                return false;
            }
        }
        return true;
    }
}
