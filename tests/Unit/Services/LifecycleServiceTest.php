<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\CustomerLifecycleStage;
use App\Models\RfmScore;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\LifecycleService;
use Tests\Concerns\RefreshDatabase;

class LifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LifecycleService $service;
    protected Tenant $tenant;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(LifecycleService::class);
        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ─── 阶段迁移 ───

    public function test_transitions_customer_to_new_stage()
    {
        $record = $this->service->transitionCustomer($this->customer, 'active', '完成引导', 'manual');

        $this->assertEquals('active', $record->stage);
        $this->assertEquals('manual', $record->triggered_by);
        $this->assertEquals('完成引导', $record->reason);

        // 验证 Customer 更新
        $this->customer->refresh();
        $this->assertEquals('active', $this->customer->lifecycle_stage);
        $this->assertNotNull($this->customer->stage_entered_at);
    }

    public function test_transition_closes_previous_record()
    {
        $this->service->transitionCustomer($this->customer, 'active', '', 'auto');
        $this->service->transitionCustomer($this->customer, 'growing', '升级套餐', 'manual');

        $openRecords = CustomerLifecycleStage::where('customer_id', $this->customer->id)
            ->whereNull('exited_at')
            ->count();

        $this->assertEquals(1, $openRecords);
        $this->assertEquals('growing', CustomerLifecycleStage::whereNull('exited_at')->first()->stage);
    }

    // ─── 阶段建议 ───

    public function test_suggests_prospect_for_no_subscription_customer()
    {
        $stage = $this->service->suggestStage($this->customer);
        $this->assertEquals('prospect', $stage);
    }

    public function test_suggests_onboarding_for_recent_customer()
    {
        // 创建一个有活跃订阅且在30天内的客户
        $recentCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDays(10),
        ]);
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $recentCustomer->id,
            'status' => 'active',
        ]);

        $stage = $this->service->suggestStage($recentCustomer);
        $this->assertEquals('onboarding', $stage);
    }

    public function test_suggests_at_risk_for_grace_subscription()
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'grace',
        ]);

        // 创建超过30天
        $this->customer->update(['created_at' => now()->subDays(60)]);

        $stage = $this->service->suggestStage($this->customer);
        $this->assertEquals('at_risk', $stage);
    }

    public function test_suggests_active_for_normal_subscription()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDays(60),
        ]);
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'active',
        ]);

        $stage = $this->service->suggestStage($customer);
        $this->assertEquals('active', $stage);
    }

    // ─── 生命周期评分 ───

    public function test_get_lifecycle_score_returns_dimensions()
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'auto_renew' => true,
        ]);

        $score = $this->service->getLifecycleScore($this->customer);

        $this->assertArrayHasKey('score', $score);
        $this->assertArrayHasKey('grade', $score);
        $this->assertArrayHasKey('dimensions', $score);
        $this->assertArrayHasKey('engagement', $score['dimensions']);
        $this->assertArrayHasKey('loyalty', $score['dimensions']);
    }

    // ─── 仪表盘 ───

    public function test_get_dashboard_returns_stages()
    {
        $this->service->transitionCustomer($this->customer, 'active', '', 'auto');

        $dashboard = $this->service->getDashboard($this->tenant->id);

        $this->assertArrayHasKey('stages', $dashboard);
        $this->assertArrayHasKey('total_customers', $dashboard);
        $this->assertGreaterThanOrEqual(1, $dashboard['total_customers']);
        $this->assertArrayHasKey('active', $dashboard['stages']);
    }

    // ─── 迁移历史 ───

    public function test_get_transition_history()
    {
        $this->service->transitionCustomer($this->customer, 'active', '完成引导', 'auto');

        $history = $this->service->getTransitionHistory($this->tenant->id);

        $this->assertCount(1, $history['data']);
        $this->assertEquals('auto', $history['data'][0]['triggered_by']);
    }

    public function test_filters_transition_history_by_stage()
    {
        $customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->transitionCustomer($this->customer, 'active', '', 'auto');
        $this->service->transitionCustomer($customer2, 'churned', '未续费', 'auto');

        $filtered = $this->service->getTransitionHistory($this->tenant->id, ['stage' => 'churned']);

        $this->assertCount(1, $filtered['data']);
        $this->assertEquals('churned', $filtered['data'][0]['stage']);
    }

    // ─── 批量评估 ───

    public function test_auto_evaluate_processes_customers()
    {
        // 一个无订阅的客户（应当保持prospect）
        // 一个有活跃订阅的客户（应当变为active）
        $customer2 = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDays(60),
        ]);
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer2->id,
            'status' => 'active',
        ]);

        $result = $this->service->autoEvaluate($this->tenant->id);

        $this->assertGreaterThanOrEqual(1, $result['evaluated']);
        $this->assertGreaterThanOrEqual(1, $result['changed']);

        $customer2->refresh();
        $this->assertNotNull($customer2->lifecycle_stage);
    }
}
