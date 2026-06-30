<?php

namespace App\Services;

use App\Models\Product;
use App\Models\License;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI 智能定价建议服务 (M2-46)
 *
 * 基于需求弹性分析、竞品价格参考、历史销售数据，
 * 使用 LLM 建议最优定价、折扣策略和套餐组合。
 */
class PricingOptimizerAiService
{
    public function __construct(protected LlmService $llm) {}

    /**
     * 生成定价建议
     */
    public function suggestPricing(int $tenantId, array $options = []): array
    {
        $data = $this->collectPricingData($tenantId, $options);
        $analysis = $this->analyzeWithLlm($data, $options);

        return [
            'generated_at' => now()->toIso8601String(),
            'product_count' => count($data['products'] ?? []),
            'suggestions' => $analysis['suggestions'] ?? [],
            'discount_strategies' => $analysis['discount_strategies'] ?? [],
            'bundle_recommendations' => $analysis['bundle_recommendations'] ?? [],
            'market_positioning' => $analysis['market_positioning'] ?? [],
            'insights' => $analysis['insights'] ?? [],
        ];
    }

    /**
     * 收集定价数据
     */
    protected function collectPricingData(int $tenantId, array $options): array
    {
        $products = Product::where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        })->get(['id', 'name', 'type', 'base_price', 'metadata']);

        // License 销量统计
        $productSales = [];
        foreach ($products as $product) {
            $count = License::byTenant($tenantId)
                ->where('product_id', $product->id)
                ->count();
            $productSales[$product->id] = $count;
        }

        return [
            'products' => $products->toArray(),
            'sales_by_product' => $productSales,
            'total_licenses' => License::byTenant($tenantId)->count(),
            'active_licenses' => License::byTenant($tenantId)->where('status', 'active')->count(),
            'currency' => 'CNY',
        ];
    }

    /**
     * LLM 分析
     */
    protected function analyzeWithLlm(array $data, array $options): array
    {
        $prompt = json_encode([
            'task' => 'SaaS产品定价优化分析',
            'data' => $data,
            'requested_output' => [
                'suggestions' => '每个产品的定价建议（含产品名、当前价格、建议价格、理由、预期影响）',
                'discount_strategies' => '折扣策略建议（首年折扣、批量折扣、季节性促销等）',
                'bundle_recommendations' => '套餐捆绑建议',
                'market_positioning' => '市场定位分析',
                'insights' => '关键洞察',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => '你是SaaS定价策略专家，精通需求弹性分析和定价优化。返回JSON。'],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'temperature' => 0.4,
                'response_format' => ['type' => 'json_object'],
            ], 'pricing-optimizer');

            $content = $result['content'] ?? '{}';
            $parsed = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        } catch (\Throwable $e) {
            Log::warning('PricingOptimizerAi: LLM failed', ['error' => $e->getMessage()]);
        }

        return [
            'suggestions' => [],
            'discount_strategies' => [['name' => '新用户首年8折', 'description' => '降低首年门槛获取更多客户']],
            'bundle_recommendations' => [],
            'market_positioning' => ['分析需要更多销售数据'],
            'insights' => ['配置 LLM Provider 获取 AI 驱动的定价建议'],
        ];
    }
}
