<?php

namespace Tests\Unit\Services;

use App\Models\ChurnPrediction;
use App\Models\CsmCommunication;
use App\Models\CsmHealthScore;
use App\Models\CsmTask;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Product;
use App\Services\CsmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsmServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CsmService $service;
    protected Tenant $tenant;
    protected Customer $customer;
    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'CSM Admin',
            'email' => 'admin@test.com',
        ]);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $this->service = app(CsmService::class);
    }

    /** @test */
    public function calculates_health_score_for_customer()
    {
        $score = $this->service->calculateHealthScore($this->customer);

        $this->assertNotNull($score->id);
        $this->assertGreaterThanOrEqual(0, $score->health_score);
        $this->assertLessThanOrEqual(100, $score->health_score);
        $this->assertContains($score->health_level, ['healthy', 'attention', 'at_risk', 'churned']);
        $this->assertNotNull($score->factors);
        $this->assertNotNull($score->calculated_at);
    }

    /** @test */
    public function health_score_reflects_active_subscription()
    {
        // Customer with active subscription should score higher
        $product = Product::factory()->create();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
            'status' => 'active',
            'plan' => 'pro',
            'price' => 99.00,
            'currency' => 'USD',
            'starts_at' => now()->subMonths(3),
            'ends_at' => now()->addMonths(9),
            'auto_renew' => true,
            'next_billing_at' => now()->addMonth(),
        ]);

        $score = $this->service->calculateHealthScore($this->customer->fresh());

        $this->assertGreaterThanOrEqual(50, $score->health_score);
    }

    /** @test */
    public function health_score_lower_with_expired_subscription()
    {
        $product = Product::factory()->create();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
            'status' => 'expired',
            'plan' => 'basic',
            'price' => 29.00,
            'currency' => 'USD',
            'starts_at' => now()->subMonths(6),
            'ends_at' => now()->subMonth(),
        ]);

        $score = $this->service->calculateHealthScore($this->customer->fresh());

        $this->assertLessThan(50, $score->health_score);
    }

    /** @test */
    public function health_score_reflects_churn_prediction()
    {
        \App\Models\ChurnPrediction::create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'risk_level' => 'high',
            'churn_probability' => 0.85,
            'predicted_at' => now(),
        ]);

        $score = $this->service->calculateHealthScore($this->customer->fresh());

        $this->assertLessThanOrEqual(70, $score->health_score);
    }

    /** @test */
    public function determines_health_level()
    {
        $this->assertEquals('healthy', $this->service->determineHealthLevel(85));
        $this->assertEquals('healthy', $this->service->determineHealthLevel(80));
        $this->assertEquals('attention', $this->service->determineHealthLevel(70));
        $this->assertEquals('attention', $this->service->determineHealthLevel(60));
        $this->assertEquals('at_risk', $this->service->determineHealthLevel(45));
        $this->assertEquals('at_risk', $this->service->determineHealthLevel(30));
        $this->assertEquals('churned', $this->service->determineHealthLevel(15));
    }

    /** @test */
    public function creates_csm_task()
    {
        $task = $this->service->createTask([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->admin->id,
            'title' => '跟进续费',
            'priority' => 'high',
            'category' => 'renewal',
            'status' => 'open',
            'due_at' => now()->addDays(7),
            'created_by' => $this->admin->id,
        ]);

        $this->assertNotNull($task->id);
        $this->assertEquals('跟进续费', $task->title);
        $this->assertEquals('open', $task->status);
    }

    /** @test */
    public function completes_task_sets_completed_at()
    {
        $task = CsmTask::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->admin->id,
            'title' => '测试任务',
        ]);

        $updated = $this->service->updateTask($task, ['status' => 'completed']);

        $this->assertEquals('completed', $updated->status);
        $this->assertNotNull($updated->completed_at);
    }

    /** @test */
    public function records_communication()
    {
        $comm = $this->service->recordCommunication([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'type' => 'call',
            'subject' => '续费讨论',
            'content' => '客户表示对升级感兴趣',
        ]);

        $this->assertNotNull($comm->id);
        $this->assertEquals('call', $comm->type);
    }

    /** @test */
    public function gets_dashboard_data()
    {
        // Create some health scores
        CsmHealthScore::create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'health_score' => 90,
            'health_level' => 'healthy',
            'calculated_at' => now(),
        ]);

        $dashboard = $this->service->getDashboard($this->tenant->id);

        $this->assertArrayHasKey('health_distribution', $dashboard);
        $this->assertArrayHasKey('total_customers', $dashboard);
        $this->assertArrayHasKey('task_stats', $dashboard);
        $this->assertEquals(1, $dashboard['total_customers']);
    }

    /** @test */
    public function batch_calculates_health()
    {
        // Create additional customers
        Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $results = $this->service->batchCalculateHealthScores($this->tenant->id);

        $this->assertCount(3, $results);
        foreach ($results as $r) {
            $this->assertArrayHasKey('customer_id', $r);
            $this->assertArrayHasKey('health_score', $r);
        }
    }

    /** @test */
    public function creates_renewal_reminders()
    {
        $product = Product::factory()->create();
        $sub = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
            'status' => 'active',
            'plan' => 'pro',
            'price' => 99.00,
            'currency' => 'USD',
            'auto_renew' => true,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(11),
            'next_billing_at' => now()->addDays(10), // within 7-14 days
        ]);

        $count = $this->service->createRenewalReminders($this->tenant->id);

        $this->assertEquals(1, $count);

        // Should not create duplicate
        $count2 = $this->service->createRenewalReminders($this->tenant->id);
        $this->assertEquals(0, $count2);
    }

    /** @test */
    public function get_health_trend_returns_daily_averages()
    {
        CsmHealthScore::create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'health_score' => 80,
            'health_level' => 'healthy',
            'calculated_at' => now()->subDays(2),
        ]);
        CsmHealthScore::create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'health_score' => 60,
            'health_level' => 'attention',
            'calculated_at' => now()->subDay(),
        ]);

        $trend = $this->service->getHealthTrend($this->tenant->id, 30);

        $this->assertArrayHasKey('points', $trend);
        $this->assertCount(2, $trend['points']);
        $this->assertEquals(80, $trend['points'][0]['avg_score']);
        $this->assertEquals(60, $trend['points'][1]['avg_score']);
    }

    /** @test */
    public function get_renewal_calendar_assigns_risk_levels()
    {
        $product = Product::factory()->create();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
            'status' => 'active',
            'plan' => 'pro',
            'price' => 99.00,
            'currency' => 'USD',
            'auto_renew' => true,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(11),
            'next_billing_at' => now()->addDays(5),
        ]);

        CsmHealthScore::create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'health_score' => 85,
            'health_level' => 'healthy',
            'calculated_at' => now(),
        ]);

        $calendar = $this->service->getRenewalCalendar($this->tenant->id, now()->format('Y-m'));

        $this->assertArrayHasKey('events', $calendar);
        $this->assertArrayHasKey('summary', $calendar);
        $this->assertCount(1, $calendar['events']);
        $this->assertEquals('red', $calendar['events'][0]['risk_level']);
        $this->assertEquals(1, $calendar['summary']['red']);
    }

    /** @test */
    public function get_activity_timeline_merges_communications_and_tasks()
    {
        $this->service->recordCommunication([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'type' => 'call',
            'subject' => '续费跟进',
            'content' => '客户确认续费意向',
        ]);

        CsmTask::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->admin->id,
            'title' => '安排演示',
            'status' => 'open',
        ]);

        $timeline = $this->service->getActivityTimeline($this->tenant->id, null, 20);

        $this->assertGreaterThanOrEqual(2, count($timeline));
        $types = array_column($timeline, 'type');
        $this->assertContains('communication', $types);
        $this->assertContains('task', $types);
    }
}
