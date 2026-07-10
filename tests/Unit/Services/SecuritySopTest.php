<?php

namespace Tests\Unit\Services;

use App\Models\IpWhitelist;
use App\Models\SecurityEvent;
use App\Models\SecuritySopExecution;
use App\Models\SecuritySopTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SecurityCenterService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SecuritySopTest extends TestCase
{
    use RefreshDatabase;

    protected SecurityCenterService $service;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Tenant::find(1)) {
            $this->tenant = Tenant::factory()->create(['id' => 1]);
        } else {
            $this->tenant = Tenant::find(1);
        }

        $this->service = app(SecurityCenterService::class);
    }

    /** @test */
    public function can_create_sop_template()
    {
        $template = $this->service->createSopTemplate([
            'tenant_id' => 1,
            'name' => 'Login Failure Response',
            'slug' => 'login-failure-response-' . uniqid(),
            'severity' => 'warning',
            'status' => 'active',
            'is_auto_execute' => false,
            'trigger_conditions' => [
                'event_types' => ['login_failed'],
                'threshold' => 5,
                'time_window_minutes' => 10,
            ],
            'steps' => [
                ['order' => 1, 'action_type' => 'log_event', 'description' => '记录事件'],
                ['order' => 2, 'action_type' => 'notify_admin', 'description' => '通知管理员'],
                ['order' => 3, 'action_type' => 'block_ip', 'description' => '封禁IP'],
            ],
        ]);

        $this->assertNotNull($template->id);
        $this->assertEquals('Login Failure Response', $template->name);
        $this->assertCount(3, $template->steps);
    }

    /** @test */
    public function can_get_sop_templates()
    {
        SecuritySopTemplate::create(['tenant_id' => 1, 'name' => 'SOP A', 'slug' => 'sop-a-' . uniqid(), 'status' => 'active', 'severity' => 'warning']);
        SecuritySopTemplate::create(['tenant_id' => 1, 'name' => 'SOP B', 'slug' => 'sop-b-' . uniqid(), 'status' => 'active', 'severity' => 'critical']);

        $result = $this->service->getSopTemplates([], 20);

        $this->assertGreaterThanOrEqual(2, $result->total());

        $filtered = $this->service->getSopTemplates(['severity' => 'critical'], 20);
        $this->assertEquals(1, $filtered->total());
    }

    /** @test */
    public function matches_event_to_sop_template()
    {
        $template = SecuritySopTemplate::create([
            'tenant_id' => 1,
            'name' => 'Critical IP Block',
            'slug' => 'critical-ip-block-' . uniqid(),
            'severity' => 'critical',
            'status' => 'active',
            'is_auto_execute' => true,
            'trigger_conditions' => ['event_types' => ['ip_blocked']],
            'steps' => [
                ['order' => 1, 'action_type' => 'log_event', 'description' => '记录'],
                ['order' => 2, 'action_type' => 'block_ip', 'description' => '封禁'],
            ],
        ]);

        $event = SecurityEvent::create([
            'tenant_id' => 1,
            'event_type' => 'ip_blocked',
            'severity' => 'critical',
            'description' => 'Test event',
        ]);

        $execution = $this->service->handleSecurityEvent($event);

        $this->assertNotNull($execution);
        $this->assertEquals($template->id, $execution->sop_template_id);
        $this->assertEquals('completed', $execution->status);

        // Verify event was updated
        $event->refresh();
        $this->assertEquals($execution->id, $event->sop_execution_id);
    }

    /** @test */
    public function auto_executes_sop_steps()
    {
        $template = SecuritySopTemplate::create([
            'tenant_id' => 1,
            'name' => 'Auto Block',
            'slug' => 'auto-block-' . uniqid(),
            'severity' => 'critical',
            'status' => 'active',
            'is_auto_execute' => true,
            'steps' => [
                ['order' => 1, 'action_type' => 'log_event', 'description' => 'Log it'],
                ['order' => 2, 'action_type' => 'block_ip', 'description' => 'Block IP'],
            ],
        ]);

        $event = SecurityEvent::create([
            'tenant_id' => 1,
            'event_type' => 'suspicious_activity',
            'severity' => 'critical',
            'ip_address' => '192.168.1.100',
            'description' => 'Suspicious',
        ]);

        $execution = $this->service->handleSecurityEvent($event);

        $this->assertNotNull($execution);
        $this->assertEquals('completed', $execution->status);
        $this->assertEquals(2, $execution->completed_steps);

        // Verify IP was blocked
        $this->assertNotNull(IpWhitelist::where('ip_address', '192.168.1.100')->where('type', 'blacklist')->first());

        // Verify event status
        $event->refresh();
        $this->assertEquals('in_progress', $event->resolution_status);
    }

    /** @test */
    public function manual_execution_creates_execution_record()
    {
        $template = SecuritySopTemplate::create([
            'tenant_id' => 1,
            'name' => 'Manual SOP',
            'slug' => 'manual-sop-' . uniqid(),
            'severity' => 'warning',
            'status' => 'active',
            'is_auto_execute' => false,
            'steps' => [
                ['order' => 1, 'action_type' => 'log_event', 'description' => 'Log'],
                ['order' => 2, 'action_type' => 'notify_admin', 'description' => 'Notify'],
            ],
        ]);

        $event = SecurityEvent::create([
            'tenant_id' => 1,
            'event_type' => 'login_failed',
            'severity' => 'warning',
            'description' => 'Manual test',
        ]);

        $execution = $this->service->executeSopManually($template, $event);

        $this->assertNotNull($execution);
        $this->assertEquals('manual', $execution->triggered_by);
        $this->assertEquals('completed', $execution->status);
        $this->assertEquals(2, $execution->completed_steps);
    }

    /** @test */
    public function doesnt_match_event_with_wrong_severity()
    {
        SecuritySopTemplate::create([
            'tenant_id' => 1,
            'name' => 'Critical Only',
            'slug' => 'critical-only-' . uniqid(),
            'severity' => 'critical',
            'status' => 'active',
            'is_auto_execute' => true,
            'steps' => [['order' => 1, 'action_type' => 'log_event', 'description' => 'Log']],
        ]);

        $event = SecurityEvent::create([
            'tenant_id' => 1,
            'event_type' => 'login_failed',
            'severity' => 'info',
            'description' => 'Info event',
        ]);

        $execution = $this->service->handleSecurityEvent($event);

        $this->assertNull($execution);
    }

    /** @test */
    public function resolves_event()
    {
        $event = SecurityEvent::create([
            'tenant_id' => 1,
            'event_type' => 'suspicious_activity',
            'severity' => 'warning',
            'description' => 'To resolve',
        ]);

        $resolved = $this->service->resolveEvent($event, 'false_positive', 'Was a test', 1);

        $this->assertEquals('false_positive', $resolved->resolution_status);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertEquals('Was a test', $resolved->resolution_notes);
    }

    /** @test */
    public function evaluates_trigger_conditions()
    {
        $template = SecuritySopTemplate::create([
            'tenant_id' => 1,
            'name' => 'Threshold SOP',
            'slug' => 'threshold-sop-' . uniqid(),
            'severity' => 'warning',
            'status' => 'active',
            'is_auto_execute' => true,
            'trigger_conditions' => ['event_types' => ['login_failed'], 'threshold' => 3, 'time_window_minutes' => 60],
            'steps' => [['order' => 1, 'action_type' => 'log_event', 'description' => 'Log']],
        ]);

        // Create 3 login_failed events in the last hour
        for ($i = 0; $i < 3; $i++) {
            SecurityEvent::create([
                'tenant_id' => 1,
                'event_type' => 'login_failed',
                'severity' => 'warning',
                'description' => "Failed login {$i}",
                'created_at' => now()->subMinutes($i * 5),
            ]);
        }

        // This 4th event should trigger the SOP (threshold met)
        $triggerEvent = SecurityEvent::create([
            'tenant_id' => 1,
            'event_type' => 'login_failed',
            'severity' => 'warning',
            'description' => 'Triggering event',
        ]);

        $execution = $this->service->handleSecurityEvent($triggerEvent);

        $this->assertNotNull($execution);

        // Create a single event of different type - should NOT trigger (event_types filter)
        $differentEvent = SecurityEvent::create([
            'tenant_id' => 1,
            'event_type' => 'logout',
            'severity' => 'warning',
            'description' => 'Should not trigger',
        ]);

        $differentExecution = $this->service->handleSecurityEvent($differentEvent);
        $this->assertNull($differentExecution);
    }
}
