<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignLog;
use App\Models\MarketingCampaignStep;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MarketingCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingCampaignServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MarketingCampaignService $service;
    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(MarketingCampaignService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ─── 活动 CRUD ───

    public function test_creates_campaign()
    {
        $campaign = $this->service->createCampaign($this->tenant->id, $this->user->id, [
            'name' => '新春促销',
            'type' => 'email',
            'description' => '春节大促',
        ]);

        $this->assertEquals('新春促销', $campaign->name);
        $this->assertEquals('draft', $campaign->status);
        $this->assertEquals('email', $campaign->type);
    }

    public function test_creates_campaign_with_steps()
    {
        $campaign = $this->service->createCampaign($this->tenant->id, $this->user->id, [
            'name' => '多步骤活动',
            'type' => 'multi_channel',
            'steps' => [
                ['action_type' => 'send_email', 'config' => ['template_id' => 1]],
                ['action_type' => 'wait', 'delay_type' => 'delay', 'delay_minutes' => 1440],
                ['action_type' => 'send_sms', 'config' => ['message' => '提醒']],
            ],
        ]);

        $this->assertCount(3, $campaign->steps);
        $this->assertEquals('send_email', $campaign->steps[0]->action_type);
        $this->assertEquals('send_sms', $campaign->steps[2]->action_type);
    }

    public function test_list_campaigns()
    {
        MarketingCampaign::factory()->create(['tenant_id' => $this->tenant->id]);
        MarketingCampaign::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '第二个活动']);

        $result = $this->service->listCampaigns($this->tenant->id);
        $this->assertCount(2, $result['data']);
    }

    public function test_updates_campaign()
    {
        $campaign = $this->service->createCampaign($this->tenant->id, $this->user->id, [
            'name' => '原名', 'type' => 'email',
        ]);

        $updated = $this->service->updateCampaign($this->tenant->id, $campaign->id, ['name' => '新名称']);
        $this->assertEquals('新名称', $updated->name);
    }

    public function test_deletes_campaign()
    {
        $campaign = $this->service->createCampaign($this->tenant->id, $this->user->id, [
            'name' => '待删除', 'type' => 'sms',
        ]);

        $this->service->deleteCampaign($this->tenant->id, $campaign->id);
        $this->assertNull(MarketingCampaign::find($campaign->id));
    }

    // ─── 活动生命周期 ───

    public function test_launch_campaign()
    {
        $campaign = $this->service->createCampaign($this->tenant->id, $this->user->id, [
            'name' => '启动测试', 'type' => 'email',
        ]);

        $launched = $this->service->launchCampaign($this->tenant->id, $campaign->id);
        $this->assertEquals('active', $launched->status);
        $this->assertNotNull($launched->started_at);
    }

    public function test_toggle_campaign_pauses_and_resumes()
    {
        $campaign = MarketingCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $paused = $this->service->toggleCampaign($this->tenant->id, $campaign->id);
        $this->assertEquals('paused', $paused->status);

        $resumed = $this->service->toggleCampaign($this->tenant->id, $campaign->id);
        $this->assertEquals('active', $resumed->status);
    }

    public function test_complete_campaign()
    {
        $campaign = MarketingCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $completed = $this->service->completeCampaign($this->tenant->id, $campaign->id);
        $this->assertEquals('completed', $completed->status);
        $this->assertNotNull($completed->ended_at);
    }

    // ─── 步骤管理 ───

    public function test_update_steps()
    {
        $campaign = $this->service->createCampaign($this->tenant->id, $this->user->id, [
            'name' => '步骤测试', 'type' => 'email',
        ]);

        $updated = $this->service->updateSteps($this->tenant->id, $campaign->id, [
            ['action_type' => 'send_notification'],
            ['action_type' => 'wait', 'delay_type' => 'delay', 'delay_minutes' => 60],
        ]);

        $this->assertCount(2, $updated->steps);
    }

    // ─── 受众计算 ───

    public function test_count_target_audience_all()
    {
        // 2个活跃客户
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $campaign = new MarketingCampaign();
        $campaign->audience_type = 'all';

        $count = $this->service->countTargetAudience($this->tenant->id, $campaign);
        $this->assertEquals(3, $count); // 包括 setUp 中的客户
    }

    // ─── 模拟发送 ───

    public function test_simulate_send_creates_logs()
    {
        $campaign = $this->service->createCampaign($this->tenant->id, $this->user->id, [
            'name' => '模拟活动', 'type' => 'email',
            'steps' => [
                ['action_type' => 'send_email', 'config' => ['template_id' => 1]],
            ],
        ]);

        $result = $this->service->simulateSend($this->tenant->id, $campaign->id);

        $this->assertGreaterThanOrEqual(1, $result['sent']);
        $this->assertGreaterThanOrEqual(1, MarketingCampaignLog::where('campaign_id', $campaign->id)->count());
    }

    // ─── 仪表盘 ───

    public function test_get_dashboard()
    {
        MarketingCampaign::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        MarketingCampaign::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'draft']);

        $dashboard = $this->service->getDashboard($this->tenant->id);

        $this->assertGreaterThanOrEqual(1, $dashboard['total_campaigns']);
        $this->assertGreaterThanOrEqual(1, $dashboard['active_campaigns']);
        $this->assertArrayHasKey('open_rate', $dashboard);
    }
}
