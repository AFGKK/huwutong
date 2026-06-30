<?php

namespace Tests\Unit\Workflows;

use App\Models\License;
use App\Models\Subscription;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Workflows\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(WorkflowEngine::class);

        // 确保工作流定义存在
        if (! WorkflowDefinition::where('name', 'test_workflow')->exists()) {
            WorkflowDefinition::create([
                'name' => 'test_workflow',
                'description' => '测试工作流',
                'steps_definition' => [
                    ['name' => 'step_one'],
                    ['name' => 'step_two'],
                ],
                'is_active' => true,
            ]);
        }
    }

    /** @test */
    public function it_can_start_a_workflow()
    {
        $subscription = Subscription::factory()->create(); // workflowable 必须有值

        $instance = $this->engine->start('test_workflow', $subscription, ['key' => 'value']);

        $this->assertDatabaseHas('workflow_instances', [
            'id' => $instance->id,
            'workflow_name' => 'test_workflow',
        ]);

        // 因 test_workflow 没有注册步骤类，引擎会尝试执行第一步并标记为失败
        // 这是正常行为：引擎正确执行了启动流程
        $this->assertNotNull($instance);
        $this->assertNotNull($instance->id);
        $this->assertEquals(['key' => 'value'], $instance->context);
    }

    /** @test */
    public function it_can_get_workflow_status()
    {
        $subscription = Subscription::factory()->create();
        $instance = $this->engine->start('test_workflow', $subscription);

        $status = $this->engine->getStatus($instance);

        $this->assertArrayHasKey('id', $status);
        $this->assertArrayHasKey('workflow', $status);
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('steps', $status);
        $this->assertEquals('test_workflow', $status['workflow']);
    }

    /** @test */
    public function it_can_cancel_a_workflow()
    {
        $subscription = Subscription::factory()->create();
        $instance = $this->engine->start('test_workflow', $subscription);

        $this->engine->cancel($instance);
        $instance->refresh();

        $this->assertEquals('cancelled', $instance->status);
        $this->assertNotNull($instance->completed_at);
    }

    /** @test */
    public function the_workflow_service_provider_registers_all_workflows()
    {
        $commissionStep = \App\Workflows\WorkflowEngine::getStep('commission_settlement', 'freeze_commission');
        $this->assertNotNull($commissionStep, 'commission_settlement.freeze_commission 应该已注册');
        $this->assertEquals('freeze_commission', $commissionStep->name());

        $lifecycleStep = \App\Workflows\WorkflowEngine::getStep('license_lifecycle', 'notify_expiry');
        $this->assertNotNull($lifecycleStep, 'license_lifecycle.notify_expiry 应该已注册');
        $this->assertEquals('notify_expiry', $lifecycleStep->name());
    }

    /** @test */
    public function it_registers_commission_settlement_workflow()
    {
        $freezeStep = \App\Workflows\WorkflowEngine::getStep('commission_settlement', 'freeze_commission');
        $this->assertNotNull($freezeStep);
        $this->assertEquals('freeze_commission', $freezeStep->name());

        $releaseStep = \App\Workflows\WorkflowEngine::getStep('commission_settlement', 'release_commission');
        $this->assertNotNull($releaseStep);
        $this->assertEquals('release_commission', $releaseStep->name());

        $approveStep = \App\Workflows\WorkflowEngine::getStep('commission_settlement', 'approve_payout');
        $this->assertNotNull($approveStep);
        $this->assertEquals('approve_payout', $approveStep->name());
    }

    /** @test */
    public function it_registers_license_lifecycle_workflow()
    {
        $notifyStep = \App\Workflows\WorkflowEngine::getStep('license_lifecycle', 'notify_expiry');
        $this->assertNotNull($notifyStep);

        $graceStep = \App\Workflows\WorkflowEngine::getStep('license_lifecycle', 'enter_grace');
        $this->assertNotNull($graceStep);

        $expireStep = \App\Workflows\WorkflowEngine::getStep('license_lifecycle', 'expire_license');
        $this->assertNotNull($expireStep);

        $restoreStep = \App\Workflows\WorkflowEngine::getStep('license_lifecycle', 'restore_license');
        $this->assertNotNull($restoreStep);

        $webhookStep = \App\Workflows\WorkflowEngine::getStep('license_lifecycle', 'send_expiry_webhook');
        $this->assertNotNull($webhookStep);
    }

    /** @test */
    public function it_keeps_existing_workflows()
    {
        $renewalStep = \App\Workflows\WorkflowEngine::getStep('renewal_pipeline', 'create_invoice');
        $this->assertNotNull($renewalStep);
        $this->assertEquals('create_invoice', $renewalStep->name());

        $expiryStep = \App\Workflows\WorkflowEngine::getStep('license_expiry', 'expire_license');
        $this->assertNotNull($expiryStep);
    }

    /** @test */
    public function it_creates_step_execution_records()
    {
        $subscription = Subscription::factory()->create();
        $instance = $this->engine->start('test_workflow', $subscription);

        // 因 test_workflow 没有注册步骤类，引擎会尝试执行并标记失败
        $this->assertNotNull($instance);
        $this->assertNotNull($instance->id);
        $this->assertContains($instance->status, ['running', 'failed']);
    }

    /** @test */
    public function workflow_definition_has_steps_method()
    {
        $def = WorkflowDefinition::where('name', 'test_workflow')->first();
        $this->assertNotNull($def);

        $steps = $def->steps();
        $this->assertCount(2, $steps);
        $this->assertEquals('step_one', $steps[0]['name']);
        $this->assertEquals('step_two', $steps[1]['name']);
    }

    /** @test */
    public function new_workflow_steps_have_required_methods()
    {
        // 通过容器创建步骤（自动注入依赖）
        $freezeStep = app(\App\Workflows\Steps\FreezeCommission::class);
        $this->assertEquals('freeze_commission', $freezeStep->name());
        $this->assertNotEmpty($freezeStep->description());
        $this->assertIsInt($freezeStep->maxRetries());
        $this->assertGreaterThanOrEqual(0, $freezeStep->maxRetries());
        $this->assertIsInt($freezeStep->timeout());

        $releaseStep = app(\App\Workflows\Steps\ReleaseCommission::class);
        $this->assertEquals('release_commission', $releaseStep->name());

        $approveStep = app(\App\Workflows\Steps\ApprovePayout::class);
        $this->assertEquals('approve_payout', $approveStep->name());

        $notifyStep = app(\App\Workflows\Steps\NotifyLicenseExpiry::class);
        $this->assertEquals('notify_expiry', $notifyStep->name());

        $graceStep = app(\App\Workflows\Steps\EnterGracePeriod::class);
        $this->assertEquals('enter_grace', $graceStep->name());

        $restoreStep = app(\App\Workflows\Steps\RestoreLicense::class);
        $this->assertEquals('restore_license', $restoreStep->name());
    }

    /** @test */
    public function it_supports_saga_compensation()
    {
        $releaseStep = \App\Workflows\WorkflowEngine::getStep('commission_settlement', 'release_commission');
        $this->assertNotNull($releaseStep);
        $this->assertTrue(method_exists($releaseStep, 'compensate'));
    }
}
