<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI 客户流失预警服务 (M2-44)
 *
 * 基于激活频率、续费历史、工单量、设备变化等多维特征，
 * 使用 LLM 识别高风险客户并建议干预措施。
 */
class ChurnPredictionAiService
{
    public function __construct(protected LlmService $llm) {}

    /**
     * 流失风险分析
     */
    public function analyze(int $tenantId, array $options = []): array
    {
        $customers = $this->collectCustomerData($tenantId, $options);
        $analysis = $this->analyzeWithLlm($customers, $options);

        return [
            'generated_at' => now()->toIso8601String(),
            'total_customers' => count($customers),
            'high_risk' => $analysis['high_risk'] ?? [],
            'medium_risk' => $analysis['medium_risk'] ?? [],
            'low_risk' => $analysis['low_risk'] ?? [],
            'overall_churn_risk' => $analysis['overall_churn_risk'] ?? 'low',
            'insights' => $analysis['insights'] ?? [],
            'recommendations' => $analysis['recommendations'] ?? [],
        ];
    }

    /**
     * 采集客户特征数据
     */
    protected function collectCustomerData(int $tenantId, array $options): array
    {
        $limit = $options['customer_limit'] ?? 50;
        $customers = Customer::byTenant($tenantId)
            ->with(['user', 'licenses' => function ($q) {
                $q->select('id', 'customer_id', 'status', 'activated_at', 'expires_at', 'created_at');
            }])
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($customers as $customer) {
            $licenses = $customer->licenses;
            $activeLicenses = $licenses->where('status', 'active');
            $expiredLicenses = $licenses->where('status', 'expired');

            // 最近活跃天数
            $lastActivation = $licenses->sortByDesc('activated_at')->first();
            $daysSinceLastActivity = $lastActivation
                ? now()->diffInDays($lastActivation->activated_at)
                : 999;

            // 工单统计
            $ticketCount = Ticket::where('customer_id', $customer->id)->count();
            $openTickets = Ticket::where('customer_id', $customer->id)
                ->whereIn('status', ['open', 'pending'])->count();

            $result[] = [
                'customer_id' => $customer->id,
                'name' => $customer->user?->name ?? "Customer #{$customer->id}",
                'type' => $customer->type,
                'level' => $customer->level,
                'total_licenses' => $licenses->count(),
                'active_licenses' => $activeLicenses->count(),
                'expired_licenses' => $expiredLicenses->count(),
                'days_since_last_activity' => $daysSinceLastActivity,
                'ticket_count' => $ticketCount,
                'open_tickets' => $openTickets,
                'created_days_ago' => now()->diffInDays($customer->created_at),
            ];
        }

        return $result;
    }

    /**
     * LLM 分析
     */
    protected function analyzeWithLlm(array $customers, array $options): array
    {
        if (empty($customers)) {
            return [
                'high_risk' => [], 'medium_risk' => [], 'low_risk' => [],
                'overall_churn_risk' => 'low',
                'insights' => ['暂无客户数据，无法分析'],
                'recommendations' => ['创建客户并分配 License 后重新分析'],
            ];
        }

        $prompt = json_encode([
            'task' => '客户流失风险分析，基于多维特征分类客户流失风险等级',
            'customers' => $customers,
            'requested_output' => [
                'high_risk' => '高风险客户列表（含 customer_id、风险评分0-100、风险原因、建议操作）',
                'medium_risk' => '中风险客户列表',
                'low_risk' => '低风险客户列表',
                'overall_churn_risk' => '整体流失风险: low/medium/high',
                'insights' => '关键发现列表',
                'recommendations' => '整体建议列表',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => '你是SaaS客户成功专家，擅长预测客户流失风险。请分析客户数据并返回JSON。'],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ], 'churn-prediction');

            $content = $result['content'] ?? '{}';
            $parsed = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        } catch (\Throwable $e) {
            Log::warning('ChurnPredictionAi: LLM failed', ['error' => $e->getMessage()]);
        }

        return $this->getFallbackAnalysis($customers);
    }

    /**
     * 兜底分析
     */
    protected function getFallbackAnalysis(array $customers): array
    {
        $high = []; $medium = []; $low = [];
        foreach ($customers as $c) {
            $score = 0;
            if (($c['days_since_last_activity'] ?? 999) > 90) $score += 30;
            if (($c['expired_licenses'] ?? 0) > ($c['total_licenses'] ?? 1) * 0.5) $score += 25;
            if (($c['open_tickets'] ?? 0) > 3) $score += 20;
            if (($c['active_licenses'] ?? 0) == 0) $score += 25;

            $item = [
                'customer_id' => $c['customer_id'],
                'name' => $c['name'],
                'risk_score' => $score,
                'reasons' => [],
                'suggested_action' => $score > 60 ? '优先跟进，提供优惠保留' : ($score > 30 ? '定期回访' : '保持关注'),
            ];
            if ($score > 60) $high[] = $item;
            elseif ($score > 30) $medium[] = $item;
            else $low[] = $item;
        }

        usort($high, fn($a, $b) => $b['risk_score'] - $a['risk_score']);
        usort($medium, fn($a, $b) => $b['risk_score'] - $a['risk_score']);

        return [
            'high_risk' => array_slice($high, 0, 10),
            'medium_risk' => array_slice($medium, 0, 10),
            'low_risk' => array_slice($low, 0, 10),
            'overall_churn_risk' => count($high) > count($customers) * 0.2 ? 'high' : (count($medium) > count($customers) * 0.3 ? 'medium' : 'low'),
            'insights' => ['基于规则引擎的流失分析', '配置 LLM Provider 可获取更精准的 AI 预测'],
            'recommendations' => ['关注高风险客户，主动联系提供支持'],
        ];
    }
}
