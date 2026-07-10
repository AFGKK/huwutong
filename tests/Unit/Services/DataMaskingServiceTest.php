<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\DataMaskingService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class DataMaskingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DataMaskingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataMaskingService::class);
    }

    /** @dataProvider emailProvider */
    public function test_mask_email(string $input, string $expectedCustomer, string $expectedOperator): void
    {
        $this->assertEquals($expectedCustomer, $this->service->mask('email', $input, 'customer'));
        $this->assertEquals($expectedOperator, $this->service->mask('email', $input, 'operator'));
        $this->assertEquals($input, $this->service->mask('email', $input, 'admin'));
    }

    public static function emailProvider(): array
    {
        return [
            'standard' => ['alice@example.com', 'a****@example.com', 'a***e@example.com'],
            'short name' => ['ab@test.com', 'a*@test.com', 'a*b@test.com'],
        ];
    }

    /** @dataProvider phoneProvider */
    public function test_mask_phone(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->service->mask('phone', $input, 'customer'));
        $this->assertEquals($expected, $this->service->mask('phone', $input, 'operator'));
        $this->assertEquals($input, $this->service->mask('phone', $input, 'admin'));
    }

    public static function phoneProvider(): array
    {
        return [
            'standard' => ['13800138000', '138****8000'],
            'short' => ['12345', '12345'],
        ];
    }

    /** @dataProvider ipProvider */
    public function test_mask_ip(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->service->mask('ip', $input, 'customer'));
        $this->assertEquals($expected, $this->service->mask('ip_address', $input, 'customer'));
    }

    public static function ipProvider(): array
    {
        return [
            'ipv4' => ['192.168.1.100', '192.168.*.*'],
            'ipv6' => ['2001:db8::ff00:42:8329', '2001:db8:0:****:****:****'],
        ];
    }

    /** @dataProvider nameProvider */
    public function test_mask_name(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->service->mask('name', $input, 'customer'));
        $this->assertEquals($input, $this->service->mask('name', $input, 'operator'));
        $this->assertEquals($input, $this->service->mask('name', $input, 'admin'));
    }

    public static function nameProvider(): array
    {
        return [
            'chinese 2 chars' => ['张三', '张*'],
            'chinese 3 chars' => ['李小明', '李**'],
            'single char' => ['王', '王'],
        ];
    }

    public function test_mask_id_card(): void
    {
        $input = '110101199001011234';
        $expected = '110***********1234';
        $this->assertEquals($expected, $this->service->mask('id_card', $input, 'customer'));
        $this->assertEquals($input, $this->service->mask('id_card', $input, 'admin'));
    }

    public function test_mask_address(): void
    {
        $input = '北京市海淀区中关村大街1号';
        $expected = '北京市海淀区*******';
        $this->assertEquals($expected, $this->service->mask('address', $input, 'customer'));
    }

    public function test_mask_token(): void
    {
        $input = 'abcdef1234567890xyz';
        $expected = 'abcdef12***';
        $this->assertEquals($expected, $this->service->mask('token', $input, 'customer'));
    }

    public function test_mask_array_recursively(): void
    {
        $data = [
            'id' => 1,
            'email' => 'user@example.com',
            'profile' => [
                'name' => '张三',
                'phone' => '13800138000',
            ],
            'items' => [
                ['email' => 'item1@test.com', 'title' => 'Item 1'],
            ],
        ];

        $masked = $this->service->maskArray($data, 'customer');

        $this->assertStringContainsString('***', $masked['email']);
        $this->assertStringContainsString('*', $masked['profile']['name']);
        $this->assertStringContainsString('****', $masked['profile']['phone']);
        $this->assertStringContainsString('***', $masked['items'][0]['email']);
        $this->assertEquals('Item 1', $masked['items'][0]['title']);
        $this->assertEquals(1, $masked['id']);
    }

    public function test_non_sensitive_field_not_masked(): void
    {
        $this->assertEquals('hello', $this->service->mask('title', 'hello', 'customer'));
        $this->assertEquals(42, $this->service->mask('count', 42, 'customer'));
        $this->assertEquals(3.14, $this->service->mask('pi', 3.14, 'customer'));
    }

    public function test_null_value_not_masked(): void
    {
        $this->assertNull($this->service->mask('email', null, 'customer'));
        $this->assertEquals('', $this->service->mask('email', '', 'customer'));
    }
}
