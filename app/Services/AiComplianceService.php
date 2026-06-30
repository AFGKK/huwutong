<?php

namespace App\Services;

use App\Models\AiSystemRegistry;
use App\Models\AiRiskAssessment;
use App\Models\AiDecisionLog;
use App\Models\AiBiasDetection;
use App\Models\AiTrainingDataSource;
use App\Models\AiOverrideRequest;
use App\Models\AiTransparencyDisclosure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * ISO 42001 AI 管理系统合规服务 (M2-140)
 */
class AiComplianceService
{
    // ─── 看板总览 ───

    public function dashboard(): array
    {
        return [
            'system_count' => AiSystemRegistry::count(),
            'active_systems' => AiSystemRegistry::where('is_active', true)->count(),
            'high_risk_systems' => AiSystemRegistry::whereIn('risk_level', ['high', 'critical'])->count(),
            'pending_reviews' => AiSystemRegistry::where('next_review_at', '<=', now())->count(),
            'open_bias_flags' => AiBiasDetection::whereIn('status', ['open', 'mitigated'])->count(),
            'pending_overrides' => AiOverrideRequest::where('status', 'pending')->count(),
            'total_decisions' => AiDecisionLog::count(),
            'recent_assessments' => AiRiskAssessment::where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    // ─── AI 系统清单 CRUD ───

    public function listSystems(Request $request): array
    {
        $query = AiSystemRegistry::query();
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('purpose', 'like', "%{$s}%")
                  ->orWhere('provider', 'like', "%{$s}%");
            });
        }
        if ($request->filled('risk_level')) $query->where('risk_level', $request->risk_level);
        if ($request->filled('deployment_status')) $query->where('deployment_status', $request->deployment_status);
        if ($request->has('is_active')) $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page = (int) $request->input('page', 1);
        $total = $query->count();
        $items = $query->orderByDesc('id')->skip(($page - 1) * $perPage)->take($perPage)->get();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    public function storeSystem(array $data): AiSystemRegistry
    {
        $data['next_review_at'] = now()->addDays(config('ai-compliance.risk_assessment.review_interval_days', 180));
        return AiSystemRegistry::create($data);
    }

    public function updateSystem(int $id, array $data): AiSystemRegistry
    {
        $system = AiSystemRegistry::findOrFail($id);
        $system->update($data);
        return $system->fresh();
    }

    public function deleteSystem(int $id): void
    {
        AiSystemRegistry::findOrFail($id)->delete();
    }

    // ─── 风险影响评估 ───

    public function listAssessments(int $systemId, Request $request): array
    {
        $query = AiRiskAssessment::where('ai_system_id', $systemId);
        if ($request->filled('status')) $query->where('status', $request->status);
        $total = $query->count();
        $items = $query->orderByDesc('id')->paginate(20)->items();
        return ['items' => $items, 'total' => $total];
    }

    public function storeAssessment(int $systemId, array $data): AiRiskAssessment
    {
        $data['ai_system_id'] = $systemId;
        $data['risk_score'] = round(($data['likelihood_score'] ?? 0) * ($data['impact_score'] ?? 0), 2);
        $data['assessed_at'] = now();
        return AiRiskAssessment::create($data);
    }

    // ─── 偏见检测 ───

    public function listBiasDetections(Request $request): array
    {
        $query = AiBiasDetection::with('system:id,name');
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('flagged')) $query->where('flagged', filter_var($request->flagged, FILTER_VALIDATE_BOOLEAN));
        if ($request->filled('ai_system_id')) $query->where('ai_system_id', $request->ai_system_id);
        $total = $query->count();
        $items = $query->orderByDesc('detected_at')->paginate(20)->items();
        return ['items' => $items, 'total' => $total];
    }

    public function storeBiasDetection(array $data): AiBiasDetection
    {
        $threshold = (float) config('ai-compliance.bias_detection.threshold_warning', 0.1);
        $criticalThreshold = (float) config('ai-compliance.bias_detection.threshold_critical', 0.2);
        $data['threshold'] = $threshold;
        $data['flagged'] = ($data['score'] ?? 0) > $threshold;
        $data['severity'] = ($data['score'] ?? 0) > $criticalThreshold ? 'critical' : 'warning';
        $data['detected_at'] = now();
        return AiBiasDetection::create($data);
    }

    public function mitigateBias(int $id, string $action): AiBiasDetection
    {
        $bias = AiBiasDetection::findOrFail($id);
        $bias->update([
            'mitigation_action' => $action,
            'status' => 'mitigated',
        ]);
        return $bias->fresh();
    }

    public function resolveBias(int $id): AiBiasDetection
    {
        $bias = AiBiasDetection::findOrFail($id);
        $bias->update(['status' => 'resolved', 'resolved_at' => now()]);
        return $bias->fresh();
    }

    // ─── 训练数据来源 ───

    public function listTrainingData(int $systemId): array
    {
        return AiTrainingDataSource::where('ai_system_id', $systemId)->orderByDesc('id')->get()->toArray();
    }

    public function storeTrainingData(int $systemId, array $data): AiTrainingDataSource
    {
        $data['ai_system_id'] = $systemId;
        return AiTrainingDataSource::create($data);
    }

    public function deleteTrainingData(int $id): void
    {
        AiTrainingDataSource::findOrFail($id)->delete();
    }

    // ─── 透明度披露 ───

    public function listDisclosures(int $systemId): array
    {
        return AiTransparencyDisclosure::where('ai_system_id', $systemId)->orderByDesc('id')->get()->toArray();
    }

    public function storeDisclosure(int $systemId, array $data): AiTransparencyDisclosure
    {
        $data['ai_system_id'] = $systemId;
        if (!isset($data['effective_from'])) $data['effective_from'] = now();
        return AiTransparencyDisclosure::create($data);
    }

    // ─── AI 决策审计日志 ───

    public function listDecisionLogs(Request $request): array
    {
        $query = AiDecisionLog::query()->with('system:id,name');
        if ($request->filled('ai_system_id')) $query->where('ai_system_id', $request->ai_system_id);
        if ($request->filled('decision_type')) $query->where('decision_type', $request->decision_type);
        if ($request->filled('result')) $query->where('result', $request->result);
        if ($request->filled('customer_id')) $query->where('customer_id', $request->customer_id);
        if ($request->has('was_overridden')) $query->where('was_overridden', filter_var($request->was_overridden, FILTER_VALIDATE_BOOLEAN));

        $perPage = min((int) $request->input('per_page', 25), 100);
        $page = (int) $request->input('page', 1);
        $total = $query->count();
        $items = $query->orderByDesc('occurred_at')->skip(($page - 1) * $perPage)->take($perPage)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    public function logDecision(array $data): AiDecisionLog
    {
        if (!isset($data['decision_id'])) $data['decision_id'] = (string) Str::uuid();
        if (!isset($data['occurred_at'])) $data['occurred_at'] = now();
        $data['disclosure_shown'] = config('ai-compliance.transparency.require_disclosure', true);
        return AiDecisionLog::create($data);
    }

    public function showDecisionLog(int $id): AiDecisionLog
    {
        return AiDecisionLog::with(['system:id,name', 'overrider:id,name', 'overrideRequests'])->findOrFail($id);
    }

    // ─── 人工申诉 Override ───

    public function listOverrideRequests(Request $request): array
    {
        $query = AiOverrideRequest::query();
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('escalation_level')) $query->where('escalation_level', $request->escalation_level);
        $total = $query->count();
        $items = $query->orderByDesc('submitted_at')->paginate(20)->items();
        return ['items' => $items, 'total' => $total];
    }

    public function storeOverrideRequest(array $data): AiOverrideRequest
    {
        if (!isset($data['request_id'])) $data['request_id'] = 'OV-' . strtoupper(Str::random(10));
        $data['submitted_at'] = now();
        return AiOverrideRequest::create($data);
    }

    public function processOverride(int $id, array $data): AiOverrideRequest
    {
        $request = AiOverrideRequest::findOrFail($id);
        $data['resolved_at'] = now();
        $request->update($data);

        // 如果通过 override，更新关联的决策日志
        if (($data['final_decision'] ?? '') === 'override' && $request->ai_decision_log_id) {
            AiDecisionLog::where('id', $request->ai_decision_log_id)->update([
                'was_overridden' => true,
                'overridden_by' => auth()->id(),
                'overridden_at' => now(),
                'override_reason' => $data['resolution_notes'] ?? '人工申诉通过',
            ]);
        }

        return $request->fresh();
    }

    // ─── 合规差距分析 ───

    public function gapAnalysis(): array
    {
        $systems = AiSystemRegistry::where('is_active', true)->get();
        $gaps = [];

        foreach ($systems as $system) {
            $systemGaps = [];

            // 检查是否完成风险评估
            $hasAssessment = AiRiskAssessment::where('ai_system_id', $system->id)
                ->where('status', 'completed')->exists();
            if (!$hasAssessment) $systemGaps[] = '缺失风险评估';

            // 检查是否配置了透明度披露
            $hasDisclosure = AiTransparencyDisclosure::where('ai_system_id', $system->id)
                ->where('is_active', true)->exists();
            if (!$hasDisclosure) $systemGaps[] = '未配置透明度披露';

            // 检查是否记录了训练数据来源
            $hasTrainingData = AiTrainingDataSource::where('ai_system_id', $system->id)->exists();
            if (!$hasTrainingData) $systemGaps[] = '未记录训练数据来源';

            // 检查上次评审是否过期
            if ($system->next_review_at && $system->next_review_at->isPast()) {
                $systemGaps[] = '评审已过期(' . $system->next_review_at->format('Y-m-d') . ')';
            }

            if (!empty($systemGaps)) {
                $gaps[] = [
                    'system_id' => $system->id,
                    'system_name' => $system->name,
                    'risk_level' => $system->risk_level,
                    'gaps' => $systemGaps,
                    'gap_count' => count($systemGaps),
                ];
            }
        }

        // 全局差距
        $globalGaps = [];

        $totalSystems = $systems->count();
        $assessedSystems = AiRiskAssessment::where('status', 'completed')
            ->distinct('ai_system_id')->count('ai_system_id');
        if ($totalSystems > 0 && $assessedSystems < $totalSystems) {
            $coverageRate = round(($assessedSystems / $totalSystems) * 100);
            if ($coverageRate < 80) $globalGaps[] = "风险评估覆盖率仅 {$coverageRate}%（{$assessedSystems}/{$totalSystems}）";
        }

        return [
            'system_gaps' => $gaps,
            'total_gaps' => count($gaps),
            'global_gaps' => $globalGaps,
            'compliance_score' => $this->calculateComplianceScore($systems->count()),
        ];
    }

    protected function calculateComplianceScore(int $totalSystems): array
    {
        if ($totalSystems === 0) return ['score' => 100, 'level' => 'compliant'];

        $deductions = 0;
        $now = now();

        // 未评估扣分
        $assessedCount = AiRiskAssessment::where('status', 'completed')
            ->distinct('ai_system_id')->count('ai_system_id');
        $deductions += ($totalSystems - $assessedCount) * 15;

        // 过期评审扣分
        $overdueCount = AiSystemRegistry::where('is_active', true)
            ->where('next_review_at', '<', $now)->count();
        $deductions += $overdueCount * 10;

        // 未解决的偏见扣分
        $openBias = AiBiasDetection::whereIn('status', ['open', 'mitigated'])->count();
        $deductions += $openBias * 5;

        // 待处理申诉扣分
        $pendingOverrides = AiOverrideRequest::where('status', 'pending')->count();
        $deductions += $pendingOverrides * 3;

        // 缺少透明度披露扣分
        $systemsWithDisclosure = AiTransparencyDisclosure::where('is_active', true)
            ->distinct('ai_system_id')->count('ai_system_id');
        $deductions += ($totalSystems - $systemsWithDisclosure) * 10;

        $score = max(0, 100 - $deductions);
        $level = $score >= 80 ? 'compliant' : ($score >= 50 ? 'partial' : 'non_compliant');

        return [
            'score' => $score,
            'level' => $level,
            'label' => $level === 'compliant' ? '合规' : ($level === 'partial' ? '部分合规' : '不合规'),
        ];
    }

    // ─── 合规报告 ───

    public function complianceReport(): array
    {
        $gapAnalysis = $this->gapAnalysis();
        $dashboard = $this->dashboard();

        return [
            'generated_at' => now()->toDateTimeString(),
            'summary' => $dashboard,
            'gap_analysis' => $gapAnalysis,
            'systems' => AiSystemRegistry::where('is_active', true)
                ->withCount(['riskAssessments', 'biasDetections', 'decisionLogs'])
                ->get(),
        ];
    }
}
