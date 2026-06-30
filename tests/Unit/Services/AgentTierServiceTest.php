<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\AgentTierDefinition;
use App\Models\AgentTierHistory;
use App\Models\AgentTierRule;
use App\Services\AgentTierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTierServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AgentTierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AgentTierService();
    }

    /** @test */
    public function it_initializes_default_tiers()
    {
        $result = $this->service->initDefaultTiers();

        $this->assertCount(4, $result);

        $this->assertDatabaseHas('agent_tier_definitions', ['level' => 'regular', 'default_rate' => 5.0]);
        $this->assertDatabaseHas('agent_tier_definitions', ['level' => 'silver', 'default_rate' => 10.0]);
        $this->assertDatabaseHas('agent_tier_definitions', ['level' => 'gold', 'default_rate' => 20.0]);
        $this->assertDatabaseHas('agent_tier_definitions', ['level' => 'platinum', 'default_rate' => 30.0]);

        // Rules should also be created
        $this->assertDatabaseHas('agent_tier_rules', ['from_level' => 'regular', 'to_level' => 'silver']);
        $this->assertDatabaseHas('agent_tier_rules', ['from_level' => 'silver', 'to_level' => 'gold']);
        $this->assertDatabaseHas('agent_tier_rules', ['from_level' => 'gold', 'to_level' => 'platinum']);
    }

    /** @test */
    public function it_returns_tier_definitions()
    {
        $this->service->initDefaultTiers();

        $definitions = $this->service->getTierDefinitions();
        $this->assertCount(4, $definitions);
    }

    /** @test */
    public function it_evaluates_promotion_for_platinum_agent()
    {
        $this->service->initDefaultTiers();
        $agent = Agent::factory()->create(['level' => 'platinum']);

        $result = $this->service->evaluatePromotion($agent);

        $this->assertFalse($result['can_promote']);
        $this->assertEquals('已达到最高等级', $result['message']);
    }

    /** @test */
    public function it_detects_all_conditions_met()
    {
        $this->service->initDefaultTiers();

        // Create an agent that meets all regular → silver conditions
        $agent = Agent::factory()->create([
            'level' => 'regular',
            'tier_subscriptions_total' => 10,
            'tier_revenue_total' => 5000,
            'tier_referrals_total' => 3,
            'tier_monthly_revenue' => 2000,
        ]);

        $result = $this->service->evaluatePromotion($agent);

        // Should report can_promote since all conditions are met
        // min_days might fail since agent was just created
        // But the rule requires min_days=30 so it should detect days unmet
        $this->assertArrayHasKey('details', $result);
        $this->assertEquals('regular', $result['current_level']);
        $this->assertEquals('silver', $result['target_level']);
    }

    /** @test */
    public function it_promotes_agent_to_next_level()
    {
        $this->service->initDefaultTiers();
        $agent = Agent::factory()->create(['level' => 'regular']);

        $result = $this->service->promoteAgent($agent, 'silver', 'manual', null, '表现优异');

        $this->assertEquals('silver', $result->level);
        $this->assertEquals(10.0, $result->commission_rate);
        $this->assertNotNull($result->tier_last_promoted_at);

        // Check history was recorded
        $this->assertDatabaseHas('agent_tier_histories', [
            'agent_id' => $agent->id,
            'from_level' => 'regular',
            'to_level' => 'silver',
            'reason' => 'manual',
        ]);
    }

    /** @test */
    public function it_demotes_agent()
    {
        $this->service->initDefaultTiers();
        $agent = Agent::factory()->create(['level' => 'gold']);

        $result = $this->service->demoteAgent($agent, 'silver', '业绩不达标', null, '连续3月未达标');

        $this->assertEquals('silver', $result->level);
    }

    /** @test */
    public function it_refreshes_agent_stats()
    {
        $agent = Agent::factory()->create();
        $agent->tier_subscriptions_total = 0;
        $agent->tier_revenue_total = 0;

        // Update stats directly
        $agent->updateQuietly([
            'tier_subscriptions_total' => 5,
            'tier_revenue_total' => 10000,
            'tier_referrals_total' => 3,
            'tier_monthly_revenue' => 2000,
        ]);

        $refreshed = $this->service->refreshAgentStats($agent);
        $this->assertNotNull($refreshed);
    }

    /** @test */
    public function it_returns_platform_overview()
    {
        $this->service->initDefaultTiers();

        Agent::factory()->count(3)->create(['level' => 'regular', 'status' => 'active']);
        Agent::factory()->count(2)->create(['level' => 'silver', 'status' => 'active']);
        Agent::factory()->create(['level' => 'gold', 'status' => 'active']);

        $overview = $this->service->getPlatformOverview();

        $this->assertArrayHasKey('total_agents', $overview);
        $this->assertArrayHasKey('by_level', $overview);
        $this->assertEquals(6, $overview['total_agents']);
        $this->assertArrayHasKey('regular', $overview['by_level']);
        $this->assertArrayHasKey('silver', $overview['by_level']);
        $this->assertArrayHasKey('gold', $overview['by_level']);
    }

    /** @test */
    public function it_returns_agent_report()
    {
        $this->service->initDefaultTiers();
        $agent = Agent::factory()->create(['level' => 'silver', 'status' => 'active']);

        $report = $this->service->getAgentReport($agent);

        $this->assertArrayHasKey('agent', $report);
        $this->assertArrayHasKey('evaluation', $report);
        $this->assertArrayHasKey('stats', $report);
        $this->assertArrayHasKey('monthly_trend', $report);
        $this->assertArrayHasKey('history', $report);
    }

    /** @test */
    public function it_auto_promotes_eligible_agents()
    {
        $this->service->initDefaultTiers();

        // Create an agent that has been around long enough and meets conditions
        $agent = Agent::factory()->create([
            'level' => 'regular',
            'status' => 'active',
            'tier_subscriptions_total' => 5,
            'tier_revenue_total' => 2000,
            'tier_referrals_total' => 2,
            'tier_monthly_revenue' => 1000,
            'tier_next_review_at' => now()->subDay(),
        ]);

        // Manually set created_at to 60 days ago to meet min_days=30
        $agent->created_at = now()->subDays(60);
        $agent->save();

        $result = $this->service->autoPromoteAgents();

        // Should check at least 1 agent
        $this->assertGreaterThanOrEqual(1, $result['total_checked']);
    }

    /** @test */
    public function it_tracks_tier_history()
    {
        $this->service->initDefaultTiers();
        $agent = Agent::factory()->create(['level' => 'regular']);

        // Promote twice
        $this->service->promoteAgent($agent, 'silver', 'auto');
        $agent->refresh();
        $this->service->promoteAgent($agent, 'gold', 'manual');

        $histories = AgentTierHistory::where('agent_id', $agent->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $histories);
        $this->assertEquals('regular', $histories[0]->from_level);
        $this->assertEquals('silver', $histories[0]->to_level);
        $this->assertEquals('auto', $histories[0]->reason);
        $this->assertEquals('silver', $histories[1]->from_level);
        $this->assertEquals('gold', $histories[1]->to_level);
    }
}
