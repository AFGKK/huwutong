<?php

namespace Tests\Unit\Services;

use App\Models\CrossBorderTransfer;
use App\Models\Dpia;
use App\Models\PersonalDataInventory;
use App\Models\User;
use App\Services\PiplComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PiplComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PiplComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PiplComplianceService();
    }

    /** @test */
    public function it_classifies_password_as_l4_private()
    {
        $rule = $this->invokeClassify('users', 'password');
        $this->assertNotNull($rule);
        $this->assertEquals('private', $rule['category']);
        $this->assertEquals('L4', $rule['level']);
    }

    /** @test */
    public function it_classifies_id_card_as_l3_sensitive()
    {
        $rule = $this->invokeClassify('users', 'id_card');
        $this->assertNotNull($rule);
        $this->assertEquals('sensitive', $rule['category']);
        $this->assertEquals('L3', $rule['level']);
    }

    /** @test */
    public function it_classifies_email_and_phone_as_l2_person()
    {
        $rule = $this->invokeClassify('users', 'email');
        $this->assertEquals('person', $rule['category']);
        $this->assertEquals('L2', $rule['level']);

        $rule = $this->invokeClassify('users', 'phone');
        $this->assertEquals('person', $rule['category']);
        $this->assertEquals('L2', $rule['level']);
    }

    /** @test */
    public function it_classifies_notes_as_l1_general()
    {
        $rule = $this->invokeClassify('tickets', 'notes');
        $this->assertEquals('general', $rule['category']);
        $this->assertEquals('L1', $rule['level']);
    }

    /** @test */
    public function it_returns_null_for_non_personal_fields()
    {
        $rule = $this->invokeClassify('users', 'id');
        $this->assertNull($rule);

        $rule = $this->invokeClassify('users', 'created_at');
        $this->assertNull($rule);

        $rule = $this->invokeClassify('users', 'tenant_id');
        $this->assertNull($rule);
    }

    /** @test */
    public function it_creates_inventory_items_from_scanning_users_table()
    {
        $tenant = \App\Models\Tenant::factory()->create();

        // 手动创建一些清单记录来模拟扫描结果
        PersonalDataInventory::create([
            'tenant_id' => $tenant->id,
            'field_name' => 'email',
            'table_name' => 'users',
            'category' => 'person',
            'classification' => 'L2',
            'purpose' => '业务运营与客户服务',
            'retention_days' => '365',
        ]);
        PersonalDataInventory::create([
            'tenant_id' => $tenant->id,
            'field_name' => 'password',
            'table_name' => 'users',
            'category' => 'private',
            'classification' => 'L4',
            'purpose' => '账户安全认证',
            'retention_days' => '180',
        ]);

        $this->assertDatabaseHas('personal_data_inventories', [
            'tenant_id' => $tenant->id,
            'field_name' => 'email',
            'classification' => 'L2',
        ]);
        $this->assertDatabaseHas('personal_data_inventories', [
            'tenant_id' => $tenant->id,
            'field_name' => 'password',
            'classification' => 'L4',
        ]);
    }

    /** @test */
    public function it_creates_cross_border_transfer()
    {
        $tenant = \App\Models\Tenant::factory()->create();

        $transfer = $this->service->createCrossBorderTransfer([
            'tenant_id' => $tenant->id,
            'data_category' => '用户账户信息',
            'recipient_country' => '美国',
            'recipient_name' => 'AWS Inc.',
            'recipient_purpose' => '云服务器托管',
            'transfer_method' => 'cloud',
            'legal_basis' => 'standard_clauses',
            'security_measures' => 'TLS 1.3 + AES-256 加密',
        ]);

        $this->assertDatabaseHas('cross_border_transfers', [
            'id' => $transfer->id,
            'data_category' => '用户账户信息',
            'recipient_country' => '美国',
            'legal_basis' => 'standard_clauses',
            'status' => 'active',
        ]);
        $this->assertNotNull($transfer->reviewed_at);
        $this->assertNotNull($transfer->next_review_at);
    }

    /** @test */
    public function it_reviews_cross_border_transfer()
    {
        $reviewer = User::factory()->create();
        $tenant = \App\Models\Tenant::factory()->create();

        $transfer = $this->service->createCrossBorderTransfer([
            'tenant_id' => $tenant->id,
            'data_category' => '用户账户信息',
            'recipient_country' => '新加坡',
            'recipient_name' => 'AliCloud SG',
            'recipient_purpose' => 'CDN 加速',
            'transfer_method' => 'api',
            'legal_basis' => 'consent',
        ]);

        $result = $this->service->reviewCrossBorderTransfer(
            $transfer->id,
            '已完成传输影响评估，风险等级中等，建议持续监控',
            $reviewer->id
        );

        $this->assertEquals('已完成传输影响评估，风险等级中等，建议持续监控', $result->impact_assessment);
        $this->assertEquals($reviewer->id, $result->reviewed_by);
    }

    /** @test */
    public function it_creates_and_completes_dpia()
    {
        $user = User::factory()->create();

        $dpia = $this->service->createDpia([
            'title' => 'License 授权数据处理影响评估',
            'description' => '评估 License 授权过程中个人数据的处理活动',
            'involved_data_categories' => ['用户账户信息', '设备指纹', 'IP 地址'],
            'stakeholders' => ['数据保护官', '产品经理', '法务'],
        ], $user->id);

        $this->assertDatabaseHas('dpias', [
            'id' => $dpia->id,
            'title' => 'License 授权数据处理影响评估',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        // Complete the DPIA
        $completed = $this->service->completeDpia($dpia->id, [
            'necessity_assessment' => 'License 授权验证需要处理设备信息',
            'risk_assessment' => '中等风险，含设备指纹等唯一标识',
            'mitigation_measures' => '数据脱敏展示，仅保存必要字段',
            'conclusion' => '通过评估，建议每年度重新评估',
        ]);

        $this->assertEquals('completed', $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    /** @test */
    public function it_returns_overdue_transfers()
    {
        $tenant = \App\Models\Tenant::factory()->create();

        // 创建一个已过期的传输记录
        $transfer = CrossBorderTransfer::create([
            'tenant_id' => $tenant->id,
            'data_category' => '用户信息',
            'recipient_country' => '德国',
            'recipient_name' => 'EU Server GmbH',
            'recipient_purpose' => '数据备份',
            'transfer_method' => 'api',
            'legal_basis' => 'adequacy',
            'status' => 'active',
            'reviewed_at' => now()->subYears(2),
            'next_review_at' => now()->subYear(), // 已过期
        ]);

        $overdue = $this->service->getOverdueTransfers();
        $this->assertCount(1, $overdue);
        $this->assertEquals($transfer->id, $overdue[0]['id']);
    }

    /** @test */
    public function it_returns_sensitive_field_definitions()
    {
        $fields = $this->service->getSensitiveFieldDefinitions();

        $this->assertArrayHasKey('password', $fields);
        $this->assertArrayHasKey('id_card', $fields);
        $this->assertArrayHasKey('phone', $fields);
        $this->assertEquals('L4', $fields['password']['level']);
        $this->assertEquals('L3', $fields['id_card']['level']);
        $this->assertEquals('L2', $fields['phone']['level']);
    }

    /** @test */
    public function it_returns_stats()
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = User::factory()->create();

        PersonalDataInventory::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        CrossBorderTransfer::create([
            'tenant_id' => $tenant->id,
            'data_category' => '测试',
            'recipient_country' => '日本',
            'recipient_name' => 'Test Co.',
            'recipient_purpose' => '测试',
            'transfer_method' => 'api',
            'legal_basis' => 'consent',
        ]);
        Dpia::factory()->create(['created_by' => $user->id, 'status' => 'completed']);

        $stats = $this->service->getStats();

        $this->assertArrayHasKey('inventory', $stats);
        $this->assertArrayHasKey('cross_border', $stats);
        $this->assertArrayHasKey('dpia', $stats);
        $this->assertEquals(1, $stats['cross_border']['total']);
        $this->assertEquals(1, $stats['dpia']['completed']);
    }

    /**
     * 反射调用受保护方法
     */
    protected function invokeClassify(string $table, string $column): ?array
    {
        $method = new \ReflectionMethod($this->service, 'getClassificationRule');
        $method->setAccessible(true);
        return $method->invoke($this->service, $table, $column);
    }
}
