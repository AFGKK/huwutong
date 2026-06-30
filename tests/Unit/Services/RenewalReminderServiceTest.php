<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\RenewalReminderLog;
use App\Models\RenewalReminderTemplate;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\RenewalReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RenewalReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RenewalReminderService $service;
    protected Tenant $tenant;
    protected Customer $customer;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(RenewalReminderService::class);
        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'auto_renew' => false,
            'ends_at' => now()->addDays(30),
        ]);
    }

    // ─── 模板管理 ───

    public function test_lists_templates()
    {
        RenewalReminderTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        RenewalReminderTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'channel' => 'sms']);

        $result = $this->service->listTemplates($this->tenant->id);
        $this->assertCount(2, $result['data']);
    }

    public function test_creates_template()
    {
        $tmpl = $this->service->createTemplate([
            'tenant_id' => $this->tenant->id,
            'name' => '7天到期提醒',
            'channel' => 'mail',
            'days_before' => 7,
            'subject' => '您的订阅即将到期',
            'content' => '您好，请及时续费。',
        ]);

        $this->assertEquals('7天到期提醒', $tmpl->name);
        $this->assertTrue($tmpl->is_active);
    }

    public function test_updates_template()
    {
        $tmpl = RenewalReminderTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'days_before' => 7]);

        $updated = $this->service->updateTemplate($tmpl, ['days_before' => 14, 'is_active' => false]);

        $this->assertEquals(14, $updated->days_before);
        $this->assertFalse($updated->is_active);
    }

    public function test_deletes_template()
    {
        $tmpl = RenewalReminderTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->deleteTemplate($tmpl);

        $this->assertDatabaseMissing('renewal_reminder_templates', ['id' => $tmpl->id]);
    }

    // ─── 提醒发送 ───

    public function test_sends_reminder_and_creates_log()
    {
        $template = RenewalReminderTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'mail',
            'days_before' => 7,
            'subject' => '续费提醒-{{plan}}',
        ]);

        $log = $this->service->sendReminder($this->subscription, $template);

        $this->assertEquals('sent', $log->status);
        $this->assertEquals($this->subscription->id, $log->subscription_id);
        $this->assertNotNull($log->sent_at);
    }

    public function test_send_reminder_handles_failure()
    {
        $template = RenewalReminderTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'invalid_channel',
            'days_before' => 7,
        ]);

        $log = $this->service->sendReminder($this->subscription, $template);

        $this->assertEquals('failed', $log->status);
        $this->assertNotNull($log->error);
    }

    // ─── 发送记录 ───

    public function test_lists_reminder_logs()
    {
        RenewalReminderLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $this->subscription->id,
            'customer_id' => $this->customer->id,
            'channel' => 'mail',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $result = $this->service->listReminderLogs($this->tenant->id);
        $this->assertCount(1, $result['data']);
    }

    // ─── 分析优化 ───

    public function test_get_conversion_analytics()
    {
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'auto_renew' => true,
        ]);

        $analytics = $this->service->getConversionAnalytics($this->tenant->id);

        $this->assertArrayHasKey('auto_renew_rate', $analytics);
        $this->assertArrayHasKey('total_active', $analytics);
        $this->assertArrayHasKey('channel_stats', $analytics);
    }

    public function test_get_optimization_suggestions()
    {
        // No templates → should suggest missing channels
        $suggestions = $this->service->getOptimizationSuggestions($this->tenant->id);

        $this->assertNotEmpty($suggestions);
        $missingTypes = array_column($suggestions, 'type');
        $this->assertContains('missing_channel', $missingTypes);
    }

    // ─── 到期提醒调度 ───

    public function test_get_due_reminders_returns_matching_subscriptions()
    {
        // 创建一个到期日在30天后的订阅，且有一个30天前提醒模板
        $template = RenewalReminderTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'mail',
            'days_before' => 30,
            'is_active' => true,
        ]);

        // 调整订阅到期日为30天后
        $this->subscription->update([
            'ends_at' => now()->addDays(30)->startOfDay(),
            'auto_renew' => false,
        ]);

        $due = $this->service->getDueReminders($this->tenant->id);

        $this->assertCount(1, $due);
        $this->assertEquals($this->subscription->id, $due->first()['subscription']->id);
    }

    public function test_process_due_reminders_sends_for_matching()
    {
        // 创建模板，到期日匹配
        RenewalReminderTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'mail',
            'days_before' => 30,
            'is_active' => true,
        ]);

        $this->subscription->update([
            'ends_at' => now()->addDays(30)->startOfDay(),
        ]);

        $results = $this->service->processDueReminders($this->tenant->id);

        $this->assertEquals(1, $results['total']);
        $this->assertEquals(1, $results['sent']);
    }
}
