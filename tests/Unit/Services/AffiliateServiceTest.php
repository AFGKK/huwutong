<?php

namespace Tests\Unit\Services;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateClick;
use App\Models\AffiliateCreative;
use App\Models\AffiliateTree;
use App\Models\Agent;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AffiliateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AffiliateService();
    }

    /** @test */
    public function it_creates_an_affiliate_campaign()
    {
        $user = User::factory()->create();

        $campaign = $this->service->createCampaign([
            'name' => '夏季推广活动',
            'slug' => 'summer-2026',
            'type' => 'referral',
            'reward_first' => 50,
            'budget_total' => 10000,
            'max_participants' => 100,
        ], $user->id);

        $this->assertDatabaseHas('affiliate_campaigns', [
            'id' => $campaign->id,
            'name' => '夏季推广活动',
            'status' => 'draft',
        ]);
    }

    /** @test */
    public function it_refreshes_campaign_stats()
    {
        $campaign = AffiliateCampaign::factory()->create();

        // Create clicks and conversions
        AffiliateClick::factory()->count(5)->create([
            'campaign_id' => $campaign->id,
            'converted' => false,
        ]);
        AffiliateClick::factory()->count(3)->create([
            'campaign_id' => $campaign->id,
            'converted' => true,
            'commission_amount' => 100,
        ]);

        $refreshed = $this->service->refreshCampaignStats($campaign);

        $this->assertEquals(3, $refreshed->conversion_count);
        $this->assertEquals(300, $refreshed->budget_used);
    }

    /** @test */
    public function it_builds_affiliate_tree()
    {
        $parent = Agent::factory()->create();
        $child = Agent::factory()->create();

        $tree = $this->service->buildAffiliateTree($parent, $child, 1);

        $this->assertDatabaseHas('affiliate_tree', [
            'parent_agent_id' => $parent->id,
            'child_agent_id' => $child->id,
            'level' => 1,
            'rate' => AffiliateService::COMMISSION_PARENT_LEVEL1_RATE,
        ]);

        // Child's parent_agent_id should be updated (done by controller typically)
        $this->assertNotNull($tree);
    }

    /** @test */
    public function it_builds_two_level_tree()
    {
        $grandparent = Agent::factory()->create();
        $parent = Agent::factory()->create(['parent_agent_id' => $grandparent->id]);
        $child = Agent::factory()->create();

        // Build level 1: parent → child
        $this->service->buildAffiliateTree($parent, $child, 1);

        // Should auto-create level 2: grandparent → child
        $this->assertDatabaseHas('affiliate_tree', [
            'parent_agent_id' => $grandparent->id,
            'child_agent_id' => $child->id,
            'level' => 2,
        ]);
    }

    /** @test */
    public function it_returns_upline()
    {
        $parent = Agent::factory()->create();
        $child = Agent::factory()->create();

        $this->service->buildAffiliateTree($parent, $child, 1);

        $upline = $this->service->getUpline($child);

        $this->assertCount(1, $upline);
        $this->assertEquals($parent->id, $upline[0]['parent_agent_id']);
    }

    /** @test */
    public function it_records_click()
    {
        $agent = Agent::factory()->create();
        $campaign = AffiliateCampaign::factory()->create();

        $click = $this->service->recordClick([
            'agent_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'referral_code' => 'TEST123',
            'referrer_url' => 'https://example.com',
            'landing_url' => 'https://app.example.com',
        ]);

        $this->assertDatabaseHas('affiliate_clicks', [
            'id' => $click->id,
            'agent_id' => $agent->id,
            'referral_code' => 'TEST123',
            'converted' => false,
        ]);
    }

    /** @test */
    public function it_attributes_conversion_to_click()
    {
        $agent = Agent::factory()->create();
        $user = User::factory()->create();

        $click = $this->service->recordClick([
            'agent_id' => $agent->id,
            'referral_code' => 'REFCODE',
        ]);

        $result = $this->service->attributeConversion('REFCODE', $user->id, 500);

        $this->assertNotNull($result);
        $this->assertTrue($result->converted);
        $this->assertEquals($user->id, $result->converted_user_id);
        $this->assertEquals(500, $result->commission_amount);
    }

    /** @test */
    public function it_distributes_multi_level_commission()
    {
        $level1 = Agent::factory()->create(['total_earned' => 0]);
        $level2 = Agent::factory()->create(['total_earned' => 0, 'parent_agent_id' => $level1->id]);
        $agent = Agent::factory()->create(['total_earned' => 0]);

        // Build tree: level2 → agent
        $this->service->buildAffiliateTree($level2, $agent, 1);

        // Distribute 1000 commission from agent
        $this->service->distributeMultiLevelCommission($agent->id, 1000);

        // level2 gets 10% of 1000 = 100
        $level2->refresh();
        $this->assertEquals(100, $level2->total_earned);

        // level1 gets 5% of 1000 = 50 via auto-created level 2 chain
        $level1->refresh();
        $this->assertEquals(50, $level1->total_earned);
    }

    /** @test */
    public function it_returns_dashboard_stats()
    {
        $agent = Agent::factory()->create();

        AffiliateClick::factory()->count(10)->create(['agent_id' => $agent->id, 'converted' => false]);
        AffiliateClick::factory()->count(5)->create(['agent_id' => $agent->id, 'converted' => true, 'commission_amount' => 200]);
        AffiliateCampaign::factory()->create(['status' => 'active']);

        $dashboard = $this->service->getDashboard();

        $this->assertArrayHasKey('overview', $dashboard);
        $this->assertEquals(15, $dashboard['overview']['total_clicks']);
        $this->assertEquals(5, $dashboard['overview']['total_conversions']);
        $this->assertEquals(1000, $dashboard['overview']['total_commission']);
        $this->assertEquals(1, $dashboard['overview']['active_campaigns']);
    }

    /** @test */
    public function it_returns_agent_affiliate_summary()
    {
        $agent = Agent::factory()->create();

        AffiliateClick::factory()->count(8)->create(['agent_id' => $agent->id, 'converted' => false]);
        AffiliateClick::factory()->count(2)->create(['agent_id' => $agent->id, 'converted' => true, 'commission_amount' => 300]);

        $summary = $this->service->getAgentAffiliateSummary($agent);

        $this->assertEquals(10, $summary['clicks_total']);
        $this->assertEquals(2, $summary['conversions_total']);
        $this->assertEquals(600, $summary['commission_total']);
    }

    /** @test */
    public function it_returns_creative_stats()
    {
        $campaign = AffiliateCampaign::factory()->create();

        AffiliateCreative::factory()->create([
            'campaign_id' => $campaign->id,
            'click_count' => 100,
            'conversion_count' => 10,
        ]);
        AffiliateCreative::factory()->create([
            'campaign_id' => $campaign->id,
            'click_count' => 50,
            'conversion_count' => 5,
        ]);

        $stats = $this->service->getCreativeStats($campaign->id);

        $this->assertCount(2, $stats);
        $this->assertEquals(10.0, $stats[0]['conversion_rate']);
    }
}
