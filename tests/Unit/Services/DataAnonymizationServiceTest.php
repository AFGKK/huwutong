<?php

namespace Tests\Unit\Services;

use App\Models\DataAnonymizationRule;
use App\Services\DataAnonymizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataAnonymizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DataAnonymizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DataAnonymizationService();
    }

    /** @test */
    public function it_returns_anonymization_rules_for_table()
    {
        $rules = $this->service->getAnonymizationRules('users');

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('phone', $rules);
        $this->assertEquals('chinese_name', $rules['name']);
        $this->assertEquals('email', $rules['email']);
        $this->assertEquals('phone', $rules['phone']);
    }

    /** @test */
    public function it_returns_empty_rules_for_unknown_table()
    {
        $rules = $this->service->getAnonymizationRules('non_existent_table');
        $this->assertEmpty($rules);
    }

    /** @test */
    public function custom_rules_override_default_rules()
    {
        // Create a custom rule that overrides the default
        DataAnonymizationRule::create([
            'table_name' => 'users',
            'field_name' => 'email',
            'method' => 'fixed_value',
            'is_active' => true,
        ]);

        $rules = $this->service->getAnonymizationRules('users');
        $this->assertEquals('fixed_value', $rules['email']);
    }

    /** @test */
    public function custom_rules_are_merged_with_defaults()
    {
        // Create a custom rule for a field not in defaults
        DataAnonymizationRule::create([
            'table_name' => 'users',
            'field_name' => 'bio',
            'method' => 'sentence',
            'is_active' => true,
        ]);

        $rules = $this->service->getAnonymizationRules('users');
        $this->assertArrayHasKey('bio', $rules);
        $this->assertEquals('sentence', $rules['bio']);
        $this->assertArrayHasKey('name', $rules); // default still present
        $this->assertEquals('chinese_name', $rules['name']);
    }

    /** @test */
    public function inactive_custom_rules_are_ignored()
    {
        DataAnonymizationRule::create([
            'table_name' => 'users',
            'field_name' => 'email',
            'method' => 'fixed_value',
            'is_active' => false,
        ]);

        $rules = $this->service->getAnonymizationRules('users');
        $this->assertEquals('email', $rules['email']); // default should remain
    }

    /** @test */
    public function it_anonymizes_user_data()
    {
        $userData = [
            ['id' => 1, 'name' => '张三', 'email' => 'zhangsan@example.com', 'phone' => '13800138000'],
            ['id' => 2, 'name' => '李四', 'email' => 'lisi@example.com', 'phone' => '13900139000'],
        ];

        $anonymized = $this->service->anonymizeData($userData, 'users');

        $this->assertCount(2, $anonymized);

        // IDs should remain unchanged
        $this->assertEquals(1, $anonymized[0]['id']);
        $this->assertEquals(2, $anonymized[1]['id']);

        // Sensitive fields should be anonymized
        $this->assertNotEquals('张三', $anonymized[0]['name']);
        $this->assertNotEquals('zhangsan@example.com', $anonymized[0]['email']);
        $this->assertNotEquals('13800138000', $anonymized[0]['phone']);

        // Anonymized values should look realistic
        $this->assertStringContainsString('@', $anonymized[0]['email']);
        $this->assertMatchesRegularExpression('/^1[3-9]\d{9}$/', $anonymized[0]['phone']);
    }

    /** @test */
    public function it_keeps_null_values_unchanged()
    {
        $data = [
            ['id' => 1, 'name' => null, 'email' => null],
        ];

        $anonymized = $this->service->anonymizeData($data, 'users');

        $this->assertNull($anonymized[0]['name']);
        $this->assertNull($anonymized[0]['email']);
        $this->assertEquals(1, $anonymized[0]['id']);
    }

    /** @test */
    public function it_handles_empty_data()
    {
        $result = $this->service->anonymizeData([], 'users');
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_applies_fixed_value_method()
    {
        $data = [
            ['id' => 1, 'password' => 'my_secret_password'],
        ];

        $anonymized = $this->service->anonymizeData($data, 'users');

        $this->assertEquals('[ANONYMIZED]', $anonymized[0]['password']);
    }

    /** @test */
    public function it_anonymizes_invoice_data()
    {
        $invoiceData = [
            [
                'id' => 1,
                'billing_name' => '张三',
                'billing_company' => '测试有限公司',
                'billing_email' => 'billing@test.com',
                'billing_phone' => '13800138000',
                'billing_address_line1' => '北京市海淀区中关村大街1号',
                'billing_city' => '北京',
                'billing_zip' => '100000',
                'total' => 100.00,
            ],
        ];

        $anonymized = $this->service->anonymizeData($invoiceData, 'invoices');

        $this->assertNotEquals('张三', $anonymized[0]['billing_name']);
        $this->assertNotEquals('测试有限公司', $anonymized[0]['billing_company']);
        $this->assertNotEquals('billing@test.com', $anonymized[0]['billing_email']);

        // Non-sensitive fields should stay
        $this->assertEquals(1, $anonymized[0]['id']);
        $this->assertEquals(100.00, $anonymized[0]['total']);
    }

    /** @test */
    public function it_returns_supported_tables()
    {
        $tables = $this->service->getSupportedTables();

        $this->assertIsArray($tables);
        $this->assertNotEmpty($tables);

        $tableNames = array_column($tables, 'table');
        $this->assertContains('users', $tableNames);
        $this->assertContains('invoices', $tableNames);
        $this->assertContains('customers', $tableNames);
    }

    /** @test */
    public function it_anonymizes_with_token_method()
    {
        $data = [
            ['id' => 1, 'key' => 'sk-proj-xxxxx'],
        ];

        $anonymized = $this->service->anonymizeData($data, 'api_keys');

        $this->assertNotEquals('sk-proj-xxxxx', $anonymized[0]['key']);
        // md5 generates 32 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $anonymized[0]['key']);
    }

    /** @test */
    public function it_anonymizes_ip_address()
    {
        $data = [
            ['id' => 1, 'ip_address' => '192.168.1.1'],
        ];

        $anonymized = $this->service->anonymizeData($data, 'activity_log');

        $this->assertNotEquals('192.168.1.1', $anonymized[0]['ip_address']);
        $this->assertMatchesRegularExpression('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $anonymized[0]['ip_address']);
    }

    /** @test */
    public function it_does_not_modify_data_without_rules()
    {
        $data = [
            ['id' => 1, 'some_field' => 'some_value'],
        ];

        $anonymized = $this->service->anonymizeData($data, 'non_existent_table');

        $this->assertEquals('some_value', $anonymized[0]['some_field']);
        $this->assertEquals(1, $anonymized[0]['id']);
    }

    /** @test */
    public function it_anonymizes_ticket_data()
    {
        $data = [
            ['id' => 1, 'subject' => '紧急问题', 'description' => '很长的描述内容...'],
        ];

        $anonymized = $this->service->anonymizeData($data, 'tickets');

        $this->assertNotEquals('紧急问题', $anonymized[0]['subject']);
        $this->assertNotEquals('很长的描述内容...', $anonymized[0]['description']);
        $this->assertEquals(1, $anonymized[0]['id']);
    }

    /** @test */
    public function it_differentiates_anonymization_per_table()
    {
        // Same field name, different rules per table
        $userData = [['id' => 1, 'name' => '张三']];
        $customerData = [['id' => 1, 'name' => '李四']];

        $userAnon = $this->service->anonymizeData($userData, 'users');
        $customerAnon = $this->service->anonymizeData($customerData, 'customers');

        $this->assertNotEquals('张三', $userAnon[0]['name']);
        $this->assertNotEquals('李四', $customerAnon[0]['name']);
    }
}
