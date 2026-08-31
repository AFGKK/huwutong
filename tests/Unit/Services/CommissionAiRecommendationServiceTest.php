<?php

namespace Tests\Unit\Services;

use App\Services\CommissionAiRecommendationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommissionAiRecommendationServiceTest extends TestCase
{
    protected CommissionAiRecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(CommissionAiRecommendationService::class);
    }

    #[Test]
    public function it_recommends_rate_for_low_price_referral()
    {
        $result = $this->service->recommendCommissionRate(
            productPrice: 50,
            campaignType: 'referral',
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('suggested_rate', $result);
        $this->assertArrayHasKey('rate_range', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('details', $result);

        $this->assertGreaterThan(0, $result['suggested_rate']);
        $this->assertLessThanOrEqual(80, $result['suggested_rate']);
        $this->assertGreaterThan(0, $result['confidence']);
        $this->assertLessThanOrEqual(100, $result['confidence']);

        // Rate range should contain recommended
        $this->assertContains($result['suggested_rate'], [
            $result['rate_range']['min'],
            $result['rate_range']['recommended'],
            $result['rate_range']['max'],
        ]);
    }

    #[Test]
    public function it_recommends_rate_for_premium_product()
    {
        $result = $this->service->recommendCommissionRate(
            productPrice: 5000,
            campaignType: 'commission',
        );

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, $result['suggested_rate']);
        $this->assertLessThanOrEqual(50, $result['suggested_rate']);
        $this->assertStringContainsString('佣金加成', $result['reason']);
        $this->assertStringContainsString('高端', $result['reason']);
    }

    #[Test]
    public function it_recommends_rate_for_reward_type()
    {
        $result = $this->service->recommendCommissionRate(
            campaignType: 'reward',
        );

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, $result['suggested_rate']);
        $this->assertLessThanOrEqual(80, $result['suggested_rate']);
        $this->assertStringContainsString('奖励计划', $result['reason']);
    }

    #[Test]
    public function it_provides_campaign_presets()
    {
        $presets = $this->service->getCampaignPresets();

        $this->assertIsArray($presets);
        $this->assertArrayHasKey('type_presets', $presets);
        $this->assertArrayHasKey('price_bands', $presets);
        $this->assertArrayHasKey('seasonal_factor', $presets);
        $this->assertArrayHasKey('recommended_types', $presets);

        $this->assertCount(4, $presets['type_presets']);
        $this->assertCount(4, $presets['price_bands']);
        $this->assertGreaterThan(0, $presets['seasonal_factor']);
    }

    #[Test]
    public function it_performs_efficiency_analysis()
    {
        $analysis = $this->service->analyzeCommissionEfficiency(1, 30);

        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('period_days', $analysis);
        $this->assertArrayHasKey('total_clicks', $analysis);
        $this->assertArrayHasKey('total_conversions', $analysis);
        $this->assertArrayHasKey('by_type', $analysis);
        $this->assertArrayHasKey('optimal_rates', $analysis);
        $this->assertEquals(30, $analysis['period_days']);
    }

    #[Test]
    public function it_batch_recommends()
    {
        $results = $this->service->batchRecommend(
            productIds: [1, 2],
            campaignType: 'referral',
        );

        $this->assertIsArray($results);
        foreach ($results as $productId => $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('suggested_rate', $result);
            $this->assertGreaterThan(0, $result['suggested_rate']);
        }
    }

    #[Test]
    public function it_adjusts_seasonally()
    {
        $month = (int) now()->format('n');

        $result = $this->service->recommendCommissionRate(
            productPrice: 100,
            campaignType: 'referral',
        );

        // Seasonal adjustment is always present as a factor
        $this->assertArrayHasKey('details', $result);
        $this->assertArrayHasKey('seasonal_factor', $result['details']);
        $this->assertGreaterThan(0, $result['details']['seasonal_factor']);

        // In peak months, the factor is >= 1.0
        if (in_array($month, [1, 11, 12])) {
            $this->assertGreaterThanOrEqual(1.0, $result['details']['seasonal_factor']);
            $this->assertStringContainsString('旺季', $result['reason']);
        }
    }
}
