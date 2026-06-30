<?php

namespace Tests\Unit\Services;

use App\Services\RoiCalculatorService;
use Tests\TestCase;

class RoiCalculatorServiceTest extends TestCase
{
    private RoiCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RoiCalculatorService::class);
    }

    public function test_calculates_default_roi()
    {
        $result = $this->service->calculate([]);

        $this->assertEquals('CNY', $result['currency']);
        $this->assertArrayHasKey('build', $result);
        $this->assertArrayHasKey('platform', $result);
        $this->assertArrayHasKey('roi', $result);
        $this->assertArrayHasKey('savings', $result);
        $this->assertArrayHasKey('yearly_comparison', $result);
        $this->assertCount(5, $result['yearly_comparison']);
    }

    public function test_calculates_usd()
    {
        $result = $this->service->calculate([], 'USD');

        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals('$', $result['currency_symbol']);
    }

    public function test_platform_is_cheaper_than_build()
    {
        $result = $this->service->calculate([]);

        // 默认参数下，本平台应该比自建便宜
        $this->assertGreaterThan(0, $result['savings']['year1']);
        $this->assertGreaterThan(0, $result['savings']['yearly']);
        $this->assertGreaterThan(0, $result['savings']['five_year']);
    }

    public function test_roi_percentage_is_positive()
    {
        $result = $this->service->calculate([]);

        $this->assertGreaterThan(0, $result['roi']['year1']);
        $this->assertGreaterThan(0, $result['roi']['yearly']);
        $this->assertGreaterThan(0, $result['roi']['five_year']);
    }

    public function test_break_even_months_is_reasonable()
    {
        $result = $this->service->calculate([]);

        $this->assertGreaterThan(0, $result['savings']['break_even_months']);
        $this->assertLessThan(60, $result['savings']['break_even_months']);
    }

    public function test_custom_params_affect_result()
    {
        // 大量seat应该显著提升节省
        $manySeats = $this->service->calculate(['license_fee' => 100, 'seat_count' => 1000]);
        $default = $this->service->calculate([]);

        $this->assertNotEquals($manySeats['savings']['year1'], $default['savings']['year1']);
    }

    public function test_high_dev_cost_increases_savings()
    {
        $highDev = $this->service->calculate(['developer_salary' => 500000, 'developer_count' => 5]);
        $default = $this->service->calculate([]);

        // 高开发成本应该导致更高的节省
        $this->assertGreaterThan($default['savings']['year1'], $highDev['savings']['year1']);
    }

    public function test_get_defaults_returns_all_params()
    {
        $defaults = $this->service->getDefaults('CNY');

        $this->assertArrayHasKey('developer_salary', $defaults);
        $this->assertArrayHasKey('developer_count', $defaults);
        $this->assertArrayHasKey('license_fee', $defaults);
        $this->assertArrayHasKey('seat_count', $defaults);
        $this->assertArrayHasKey('setup_fee', $defaults);
    }

    public function test_get_defaults_differs_by_currency()
    {
        $cnyDefaults = $this->service->getDefaults('CNY');
        $usdDefaults = $this->service->getDefaults('USD');

        $this->assertNotEquals($cnyDefaults['developer_salary'], $usdDefaults['developer_salary']);
        $this->assertEquals($cnyDefaults['developer_count'], $usdDefaults['developer_count']);
    }

    public function test_get_param_definitions()
    {
        $defs = $this->service->getParamDefinitions();

        $this->assertArrayHasKey('build', $defs);
        $this->assertArrayHasKey('platform', $defs);
        $this->assertArrayHasKey('developer_salary', $defs['build']);
        $this->assertArrayHasKey('license_fee', $defs['platform']);
    }

    public function test_max_devops_cost()
    {
        $result = $this->service->calculate(['devops_cost' => 200000]);

        $this->assertGreaterThan(0, $result['build']['year1']);
    }

    public function test_breakdown_entries_exist()
    {
        $result = $this->service->calculate([]);

        $this->assertArrayHasKey('development', $result['build']['breakdown']);
        $this->assertArrayHasKey('license_fee', $result['platform']['breakdown']);
        $this->assertArrayHasKey('setup_fee', $result['platform']['breakdown']);
    }
}
