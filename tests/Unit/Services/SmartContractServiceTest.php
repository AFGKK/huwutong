<?php

namespace Tests\Unit\Services;

use App\Models\LicenseContract;
use App\Models\LicenseContractAssignment;
use App\Models\LicenseContractEvaluationLog;
use App\Models\Tenant;
use App\Services\ContractConditionEngine;
use App\Services\SmartContractService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SmartContractServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SmartContractService $service;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SmartContractService(new ContractConditionEngine());
        $this->tenant = Tenant::factory()->create();
    }

    // ─── 合约管理 ───

    /** @test */
    public function it_can_create_a_contract()
    {
        $contract = $this->service->createContract([
            'tenant_id' => $this->tenant->id,
            'name' => '测试合约',
            'slug' => 'test-contract',
            'contract_type' => 'license',
            'conditions' => [
                ['type' => 'license_status', 'operator' => 'eq', 'field' => 'status', 'value' => 'active', 'label' => '状态为活跃'],
            ],
        ]);

        $this->assertInstanceOf(LicenseContract::class, $contract);
        $this->assertEquals('测试合约', $contract->name);
        $this->assertEquals('test-contract', $contract->slug);
        $this->assertEquals('license', $contract->contract_type);
    }

    /** @test */
    public function it_can_list_contracts()
    {
        LicenseContract::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        LicenseContract::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->getContracts(['tenant_id' => $this->tenant->id]);

        $this->assertCount(5, $result['data']);
        $this->assertEquals(5, $result['total']);
    }

    /** @test */
    public function it_can_update_contract()
    {
        $contract = LicenseContract::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '旧名称']);

        $this->service->updateContract($contract, ['name' => '新名称']);

        $this->assertEquals('新名称', $contract->fresh()->name);
        $this->assertEquals(2, $contract->fresh()->version);
    }

    /** @test */
    public function it_cannot_update_system_contract_name()
    {
        $contract = LicenseContract::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '系统合约', 'is_system' => true]);

        $this->service->updateContract($contract, ['name' => '新名称', 'is_active' => false]);

        $this->assertEquals('系统合约', $contract->fresh()->name);
        $this->assertFalse($contract->fresh()->is_active);
    }

    /** @test */
    public function it_cannot_delete_system_contract()
    {
        $contract = LicenseContract::factory()->create(['tenant_id' => $this->tenant->id, 'is_system' => true]);

        $result = $this->service->deleteContract($contract);

        $this->assertFalse($result);
        $this->assertNotNull(LicenseContract::find($contract->id));
    }

    /** @test */
    public function it_can_seed_system_contracts()
    {
        $count = $this->service->seedSystemContracts($this->tenant->id);

        $this->assertEquals(5, $count);
        $this->assertDatabaseHas('license_contracts', ['slug' => 'standard-device-limit']);
        $this->assertDatabaseHas('license_contracts', ['slug' => 'business-hours-access']);

        $count2 = $this->service->seedSystemContracts($this->tenant->id);
        $this->assertEquals(0, $count2);
    }

    // ─── 合约分配 ───

    /** @test */
    public function it_can_assign_contract_to_entity()
    {
        $contract = LicenseContract::factory()->create(['tenant_id' => $this->tenant->id]);

        $assignment = $this->service->assignContract([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'assignable_type' => 'App\\Models\\License',
            'assignable_id' => 1,
        ]);

        $this->assertInstanceOf(LicenseContractAssignment::class, $assignment);
        $this->assertEquals($contract->id, $assignment->contract_id);
    }

    /** @test */
    public function it_prevents_duplicate_assignments()
    {
        $contract = LicenseContract::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->assignContract(['tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'assignable_type' => 'App\\Models\\License', 'assignable_id' => 1]);
        $this->service->assignContract(['tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'assignable_type' => 'App\\Models\\License', 'assignable_id' => 1]);

        $assignments = $this->service->getAssignments($contract->id);
        $this->assertCount(1, $assignments);
    }

    /** @test */
    public function it_can_get_entity_assignments()
    {
        $contract = LicenseContract::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->assignContract(['tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'assignable_type' => 'App\\Models\\License', 'assignable_id' => 42]);
        $this->service->assignContract(['tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'assignable_type' => 'App\\Models\\License', 'assignable_id' => 99]);

        $result = $this->service->getEntityAssignments('App\\Models\\License', 42);
        $this->assertCount(1, $result);
        $this->assertEquals(42, $result[0]['assignable_id']);
    }

    // ─── 合约评估 ───

    /** @test */
    public function it_evaluates_contract_and_grants_when_conditions_met()
    {
        $contract = LicenseContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_type' => 'license',
            'evaluation_mode' => 'all',
            'conditions' => [
                ['type' => 'license_status', 'operator' => 'eq', 'field' => 'status', 'value' => 'active', 'label' => '状态为活跃'],
            ],
        ]);

        $result = $this->service->evaluateContract($contract, ['status' => 'active']);

        $this->assertTrue($result['granted']);
        $this->assertCount(1, $result['conditions_results']);
    }

    /** @test */
    public function it_evaluates_contract_and_denies_when_conditions_not_met()
    {
        $contract = LicenseContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_type' => 'license',
            'conditions' => [
                ['type' => 'license_status', 'operator' => 'eq', 'field' => 'status', 'value' => 'active', 'label' => '状态为活跃'],
            ],
        ]);

        $result = $this->service->evaluateContract($contract, ['status' => 'suspended']);

        $this->assertFalse($result['granted']);
    }

    /** @test */
    public function it_evaluates_with_any_mode()
    {
        $contract = LicenseContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'evaluation_mode' => 'any',
            'conditions' => [
                ['type' => 'license_status', 'operator' => 'eq', 'field' => 'status', 'value' => 'active', 'label' => '状态活跃'],
                ['type' => 'subscription_plan', 'operator' => 'eq', 'field' => 'plan', 'value' => 'premium', 'label' => '高级套餐'],
            ],
        ]);

        // 仅满足一个条件，any模式应该通过
        $result = $this->service->evaluateContract($contract, ['status' => 'active', 'plan' => 'basic']);
        $this->assertTrue($result['granted']);

        // 都不满足
        $result2 = $this->service->evaluateContract($contract, ['status' => 'expired', 'plan' => 'basic']);
        $this->assertFalse($result2['granted']);
    }

    /** @test */
    public function it_evaluates_time_window_condition()
    {
        $contract = LicenseContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contract_type' => 'time',
            'conditions' => [
                ['type' => 'time_window', 'operator' => 'between', 'field' => 'current_time',
                 'days' => [1, 2, 3, 4, 5], 'start_time' => '00:00', 'end_time' => '23:59',
                 'timezone' => 'UTC', 'label' => '全天'],
            ],
        ]);

        // 当前时间 + 工作日 => 应该通过
        $result = $this->service->evaluateContract($contract, [
            'current_time' => '12:00',
            'current_day' => 3, // Wednesday
        ]);
        $this->assertTrue($result['granted']);
    }

    /** @test */
    public function it_evaluates_for_entity_with_all_contracts()
    {
        $contract = LicenseContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'conditions' => [
                ['type' => 'license_status', 'operator' => 'eq', 'field' => 'status', 'value' => 'active', 'label' => '状态活跃'],
            ],
        ]);

        $this->service->assignContract([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'assignable_type' => 'App\\Models\\License',
            'assignable_id' => 100,
        ]);

        $result = $this->service->evaluateForEntity('App\\Models\\License', 100, ['status' => 'active']);

        $this->assertTrue($result['granted']);
        $this->assertCount(1, $result['evaluations']);
        $this->assertEquals(1, $result['summary']['applied']);
    }

    /** @test */
    public function it_returns_granted_when_no_contracts_assigned()
    {
        $result = $this->service->evaluateForEntity('App\\Models\\License', 999, []);

        $this->assertTrue($result['granted']);
        $this->assertEmpty($result['evaluations']);
    }

    // ─── 评估日志 ───

    /** @test */
    public function it_logs_evaluation_results()
    {
        $license = \App\Models\License::factory()->create(['tenant_id' => $this->tenant->id]);

        $contract = LicenseContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'conditions' => [
                ['type' => 'license_status', 'operator' => 'eq', 'field' => 'status', 'value' => 'active', 'label' => '状态活跃'],
            ],
        ]);

        $this->service->assignContract([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'assignable_type' => 'App\\Models\\License',
            'assignable_id' => $license->id,
        ]);

        $this->service->evaluateForEntity('App\\Models\\License', $license->id, ['status' => 'active']);

        $this->assertDatabaseHas('license_contract_evaluation_logs', [
            'contract_id' => $contract->id,
            'result' => 'granted',
        ]);
    }

    // ─── 仪表盘 ───

    /** @test */
    public function it_returns_dashboard_data()
    {
        LicenseContract::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $dashboard = $this->service->getDashboard($this->tenant->id);

        $this->assertEquals(3, $dashboard['total_contracts']);
        $this->assertEquals(3, $dashboard['active_contracts']);
    }

    // ─── 条件引擎 ───

    /** @test */
    public function condition_engine_supports_various_operators()
    {
        $engine = new ContractConditionEngine();
        $contract = LicenseContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'conditions' => [
                ['type' => 'custom_field', 'operator' => 'in', 'field' => 'role', 'value' => ['admin', 'super-admin'], 'label' => '角色检查'],
                ['type' => 'custom_field', 'operator' => 'gte', 'field' => 'score', 'value' => 80, 'label' => '分数检查'],
                ['type' => 'custom_field', 'operator' => 'contains', 'field' => 'email', 'value' => '@company.com', 'label' => '邮箱域'],
            ],
        ]);

        // 全部满足
        $result = $engine->evaluate($contract, [
            'role' => 'admin',
            'score' => 95,
            'email' => 'user@company.com',
        ]);
        $this->assertTrue($result['granted']);

        // 第三个不满足
        $result2 = $engine->evaluate($contract, [
            'role' => 'admin',
            'score' => 95,
            'email' => 'user@gmail.com',
        ]);
        $this->assertFalse($result2['granted']);
    }

    /** @test */
    public function condition_engine_supports_custom_expression()
    {
        $engine = new ContractConditionEngine();
        $contract = LicenseContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'evaluation_mode' => 'custom',
            'custom_expression' => 'cond_0 && (cond_1 || cond_2)',
            'conditions' => [
                ['type' => 'custom_field', 'operator' => 'eq', 'field' => 'is_active', 'value' => true, 'label' => '活跃'],
                ['type' => 'custom_field', 'operator' => 'gte', 'field' => 'tier', 'value' => 3, 'label' => '等级>=3'],
                ['type' => 'custom_field', 'operator' => 'eq', 'field' => 'is_vip', 'value' => true, 'label' => 'VIP'],
            ],
        ]);

        // cond_0 true, cond_1 false, cond_2 true => true && (false || true) => true
        $result = $engine->evaluate($contract, ['is_active' => true, 'tier' => 1, 'is_vip' => true]);
        $this->assertTrue($result['granted']);

        // cond_0 true, cond_1 false, cond_2 false => true && (false || false) => false
        $result2 = $engine->evaluate($contract, ['is_active' => true, 'tier' => 1, 'is_vip' => false]);
        $this->assertFalse($result2['granted']);
    }
}
