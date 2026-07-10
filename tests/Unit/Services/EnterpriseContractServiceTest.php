<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\EnterpriseContract;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnterpriseContractService;
use Tests\Concerns\RefreshDatabase;

class EnterpriseContractServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseContractService $service;
    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(EnterpriseContractService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ─── CRUD ───

    public function test_creates_contract()
    {
        $contract = $this->service->createContract($this->tenant->id, $this->user->id, [
            'name' => '年度服务合同',
            'customer_id' => $this->customer->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_value' => 100000,
        ]);

        $this->assertEquals('年度服务合同', $contract->name);
        $this->assertEquals('draft', $contract->status);
        $this->assertEquals(100000, (float) $contract->total_value);
        $this->assertStringStartsWith('CT-', $contract->contract_number);
    }

    public function test_list_contracts()
    {
        $this->service->createContract($this->tenant->id, $this->user->id, [
            'name' => '合同A', 'customer_id' => $this->customer->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ]);
        $this->service->createContract($this->tenant->id, $this->user->id, [
            'name' => '合同B', 'customer_id' => $this->customer->id,
            'start_date' => '2026-06-01', 'end_date' => '2027-05-31',
        ]);

        $result = $this->service->listContracts($this->tenant->id);
        $this->assertCount(2, $result['data']);
    }

    public function test_updates_contract()
    {
        $contract = $this->service->createContract($this->tenant->id, $this->user->id, [
            'name' => '原名', 'customer_id' => $this->customer->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ]);

        $updated = $this->service->updateContract($this->tenant->id, $contract->id, ['name' => '新名称']);
        $this->assertEquals('新名称', $updated->name);
    }

    public function test_deletes_contract()
    {
        $contract = $this->service->createContract($this->tenant->id, $this->user->id, [
            'name' => '待删除', 'customer_id' => $this->customer->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ]);

        $this->service->deleteContract($this->tenant->id, $contract->id);
        $this->assertNull(EnterpriseContract::find($contract->id));
    }

    // ─── 审批流程 ───

    public function test_submit_for_approval()
    {
        $contract = $this->service->createContract($this->tenant->id, $this->user->id, [
            'name' => '审批测试', 'customer_id' => $this->customer->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ]);

        $submitted = $this->service->submitForApproval($this->tenant->id, $contract->id);
        $this->assertEquals('pending_approval', $submitted->status);
        $this->assertEquals('pending', $submitted->approval_status);
    }

    public function test_approve_contract()
    {
        $contract = $this->service->createContract($this->tenant->id, $this->user->id, [
            'name' => '待审批', 'customer_id' => $this->customer->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ]);
        $this->service->submitForApproval($this->tenant->id, $contract->id);

        $approved = $this->service->approveContract($this->tenant->id, $contract->id, $this->user->id, 'approved', '条款合理');

        $this->assertEquals('active', $approved->status);
        $this->assertEquals('approved', $approved->approval_status);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_reject_contract()
    {
        $contract = $this->service->createContract($this->tenant->id, $this->user->id, [
            'name' => '待拒绝', 'customer_id' => $this->customer->id,
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ]);
        $this->service->submitForApproval($this->tenant->id, $contract->id);

        $rejected = $this->service->approveContract($this->tenant->id, $contract->id, $this->user->id, 'rejected', '需修改');

        $this->assertEquals('draft', $rejected->status);
        $this->assertEquals('rejected', $rejected->approval_status);
    }

    // ─── 合同操作 ───

    public function test_terminate_contract()
    {
        // 创建一个已审批的合同
        $contract = $this->createActiveContract();

        $terminated = $this->service->terminateContract($this->tenant->id, $contract->id);
        $this->assertEquals('terminated', $terminated->status);
    }

    public function test_renew_contract()
    {
        $contract = $this->createActiveContract();
        $contract->update(['auto_renew' => true]);

        $renewal = $this->service->renewContract($this->tenant->id, $contract->id);

        $this->assertStringContainsString('续签', $renewal->name);
        $this->assertEquals($renewal->id, $contract->fresh()->renewed_contract_id);
    }

    // ─── 仪表盘 ───

    public function test_get_dashboard()
    {
        $this->createActiveContract();

        $dashboard = $this->service->getDashboard($this->tenant->id);

        $this->assertGreaterThanOrEqual(1, $dashboard['total_contracts']);
        $this->assertArrayHasKey('active_contracts', $dashboard);
        $this->assertArrayHasKey('total_value', $dashboard);
    }

    // ─── 辅助方法 ───

    protected function createActiveContract(): EnterpriseContract
    {
        $contract = $this->service->createContract($this->tenant->id, $this->user->id, [
            'name' => '活跃合同', 'customer_id' => $this->customer->id,
            'start_date' => '2026-01-01', 'end_date' => '2027-12-31',
            'total_value' => 50000,
        ]);
        $this->service->submitForApproval($this->tenant->id, $contract->id);
        $this->service->approveContract($this->tenant->id, $contract->id, $this->user->id, 'approved');
        return $contract->fresh();
    }
}
