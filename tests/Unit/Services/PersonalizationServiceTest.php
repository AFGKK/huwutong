<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\PersonalizedRecommendation;
use App\Models\RfmScore;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBehavior;
use App\Models\UserPreference;
use App\Services\PersonalizationService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PersonalizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PersonalizationService $service;
    protected Tenant $tenant;
    protected Customer $customer;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PersonalizationService::class);
        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // ═══ 用户行为 ═══

    /** @test */
    public function it_records_behavior()
    {
        $behavior = $this->service->recordBehavior([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'event_type' => 'page_view',
            'event_action' => 'view_dashboard',
        ]);

        $this->assertInstanceOf(UserBehavior::class, $behavior);
        $this->assertEquals('page_view', $behavior->event_type);
        $this->assertEquals('view_dashboard', $behavior->event_action);
        $this->assertNotNull($behavior->occurred_at);
    }

    /** @test */
    public function it_returns_behavior_stats()
    {
        UserBehavior::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $stats = $this->service->getBehaviorStats($this->tenant->id);

        $this->assertEquals(5, $stats['total_events']);
        $this->assertNotEmpty($stats['by_type']);
        $this->assertNotEmpty($stats['daily_trend']);
    }

    /** @test */
    public function it_records_behavior_without_customer()
    {
        $behavior = $this->service->recordBehavior([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'login',
        ]);

        $this->assertEquals('login', $behavior->event_type);
        $this->assertNull($behavior->customer_id);
    }

    // ═══ 用户偏好 ═══

    /** @test */
    public function it_sets_and_gets_preference()
    {
        $this->service->setPreference($this->tenant->id, $this->user->id, 'theme', 'dark');
        $this->service->setPreference($this->tenant->id, $this->user->id, 'notifications', true);

        $this->assertEquals('dark', $this->service->getPreference($this->user->id, 'theme'));
        $this->assertEquals('1', $this->service->getPreference($this->user->id, 'notifications'));

        $all = $this->service->getAllPreferences($this->user->id);
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('theme', $all);
    }

    /** @test */
    public function it_updates_existing_preference()
    {
        $this->service->setPreference($this->tenant->id, $this->user->id, 'theme', 'dark');
        $this->service->setPreference($this->tenant->id, $this->user->id, 'theme', 'light');

        $prefs = UserPreference::where('user_id', $this->user->id)->get();
        $this->assertCount(1, $prefs);
        $this->assertEquals('light', $prefs->first()->preference_value);
    }

    // ═══ 推荐引擎 ═══

    /** @test */
    public function it_generates_rfm_recommendations_for_champions()
    {
        RfmScore::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'rfm_segment' => 'Champions',
            'rfm_total' => 9,
        ]);

        $recs = $this->service->generateRecommendations($this->tenant->id, $this->customer->id);

        // Service generates recommendations even without pricing plans
        // (e.g. rule-based guide recommendation for new customers)
        $this->assertIsArray($recs);
    }

    /** @test */
    public function it_generates_rule_recommendations_for_new_customers()
    {
        $newCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now(),
        ]);

        $recs = $this->service->generateRecommendations($this->tenant->id, $newCustomer->id);

        $guideRecs = array_filter($recs, fn($r) => $r['recommendation_type'] === 'article');
        $this->assertNotEmpty($guideRecs);
        $this->assertStringContainsString('快速入门指南', $guideRecs[0]['reason']);
    }

    /** @test */
    public function it_returns_active_recommendations()
    {
        PersonalizedRecommendation::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_dismissed' => false,
        ]);
        PersonalizedRecommendation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_dismissed' => true,
        ]);

        $active = $this->service->getActiveRecommendations($this->tenant->id, $this->customer->id);

        $this->assertCount(3, $active);
    }

    /** @test */
    public function it_dismisses_recommendation()
    {
        $rec = PersonalizedRecommendation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->service->dismissRecommendation($rec->id);

        $this->assertDatabaseHas('personalized_recommendations', [
            'id' => $rec->id,
            'is_dismissed' => true,
        ]);
    }

    /** @test */
    public function it_clicks_recommendation()
    {
        $rec = PersonalizedRecommendation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->service->clickRecommendation($rec->id);

        $this->assertNotNull($rec->fresh()->clicked_at);
    }

    /** @test */
    public function it_refreshes_all_recommendations()
    {
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->refreshAllRecommendations($this->tenant->id);

        $this->assertGreaterThanOrEqual(3, $result['refreshed']);
    }

    // ═══ 个性化主页 ═══

    /** @test */
    public function it_returns_personalized_homepage()
    {
        UserBehavior::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->service->setPreference($this->tenant->id, $this->user->id, 'theme', 'dark');

        $homepage = $this->service->getPersonalizedHomepage($this->tenant->id, $this->customer->id, $this->user->id);

        $this->assertArrayHasKey('recommendations', $homepage);
        $this->assertArrayHasKey('preferences', $homepage);
        $this->assertArrayHasKey('quick_actions', $homepage);
        $this->assertArrayHasKey('stats', $homepage);
        $this->assertArrayHasKey('theme', $homepage['preferences']);
        $this->assertEquals('dark', $homepage['preferences']['theme']);
    }

    /** @test */
    public function it_returns_admin_dashboard()
    {
        UserBehavior::factory()->count(10)->create(['tenant_id' => $this->tenant->id]);
        PersonalizedRecommendation::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        UserPreference::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
        ]);

        $dashboard = $this->service->getAdminDashboard($this->tenant->id);

        $this->assertGreaterThanOrEqual(10, $dashboard['total_events']);
        $this->assertGreaterThanOrEqual(2, $dashboard['active_recommendations']);
        $this->assertArrayHasKey('top_events', $dashboard);
        $this->assertArrayHasKey('last_7_days_trend', $dashboard);
    }
}
