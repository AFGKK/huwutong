<?php

namespace Tests\Unit\Services;

use App\Models\CompatibilityPlatform;
use App\Models\CompatibilityTestCase;
use App\Models\CompatibilityTestRun;
use App\Models\CompatibilityTestSuite;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CompatTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompatTestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CompatTestService $service;
    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CompatTestService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /** @test */
    public function it_initializes_platforms_from_templates()
    {
        $count = $this->service->initializeFromTemplates($this->tenant->id);

        $this->assertEquals(15, $count); // 3 PHP + 3 MySQL + 2 Redis + 4 Browser + 3 OS

        $platforms = CompatibilityPlatform::where('tenant_id', $this->tenant->id)->get();
        $this->assertCount(15, $platforms);
    }

    /** @test */
    public function it_gets_platforms_grouped_by_category()
    {
        $this->service->initializeFromTemplates($this->tenant->id);

        $platforms = $this->service->getPlatforms($this->tenant->id);

        $this->assertArrayHasKey('php', $platforms);
        $this->assertArrayHasKey('mysql', $platforms);
        $this->assertCount(3, $platforms['php']);
    }

    /** @test */
    public function it_gets_platform_templates()
    {
        $templates = $this->service->getPlatformTemplates();

        $this->assertArrayHasKey('php', $templates);
        $this->assertArrayHasKey('browser', $templates);
        $this->assertCount(5, $templates);
    }

    /** @test */
    public function it_creates_test_suite()
    {
        $suite = $this->service->createSuite($this->tenant->id, [
            'name' => '核心功能测试',
            'category' => 'integration',
            'description' => '核心业务流程兼容性测试',
            'tags' => ['核心', '支付'],
        ]);

        $this->assertNotNull($suite);
        $this->assertEquals('核心功能测试', $suite->name);
        $this->assertEquals(['核心', '支付'], $suite->tags);
    }

    /** @test */
    public function it_adds_test_case()
    {
        $suite = $this->service->createSuite($this->tenant->id, [
            'name' => 'API 兼容性测试',
            'category' => 'api',
        ]);

        $testCase = $this->service->addTestCase($suite->id, [
            'name' => '用户登录接口',
            'description' => '测试用户登录接口在 PHP 8.1/8.2/8.3 下的兼容性',
            'expected_result' => '返回 200 + token',
            'is_critical' => true,
        ]);

        $this->assertNotNull($testCase);
        $this->assertEquals('用户登录接口', $testCase->name);
        $this->assertTrue($testCase->is_critical);
    }

    /** @test */
    public function it_creates_test_run()
    {
        $this->service->initializeFromTemplates($this->tenant->id);
        $phpPlatform = CompatibilityPlatform::where('tenant_id', $this->tenant->id)
            ->where('category', 'php')->first();

        $run = $this->service->createTestRun(
            $this->tenant->id,
            [$phpPlatform->id],
            $this->user->id,
        );

        $this->assertNotNull($run);
        $this->assertEquals(CompatibilityTestRun::STATUS_PENDING, $run->status);
        $this->assertEquals(1, $run->matrixResults()->count());
        $this->assertStringStartsWith('CTR-', $run->reference);
    }

    /** @test */
    public function it_records_and_completes_test_run()
    {
        $this->service->initializeFromTemplates($this->tenant->id);
        $suite = $this->service->createSuite($this->tenant->id, ['name' => '测试套件', 'category' => 'api']);
        $testCase = $this->service->addTestCase($suite->id, ['name' => '测试用例']);
        $platform = CompatibilityPlatform::where('tenant_id', $this->tenant->id)
            ->where('category', 'php')->first();

        $run = $this->service->createTestRun($this->tenant->id, [$platform->id], $this->user->id);
        $this->service->startTestRun($run->id);

        // 记录结果
        $this->service->recordTestResult($run->id, $platform->id, $testCase->id, 'passed', null, 120.5, $this->user->id);

        $completed = $this->service->completeTestRun($run->id);

        $this->assertEquals(CompatibilityTestRun::STATUS_PASSED, $completed->status);
        $this->assertEquals(1, $completed->passed_tests);
    }

    /** @test */
    public function it_marks_run_as_failed_when_tests_fail()
    {
        $this->service->initializeFromTemplates($this->tenant->id);
        $suite = $this->service->createSuite($this->tenant->id, ['name' => '失败测试', 'category' => 'api']);
        $tc1 = $this->service->addTestCase($suite->id, ['name' => '通过的测试', 'is_critical' => false]);
        $tc2 = $this->service->addTestCase($suite->id, ['name' => '失败的测试', 'is_critical' => true]);
        $platform = CompatibilityPlatform::where('tenant_id', $this->tenant->id)
            ->where('category', 'php')->first();

        $run = $this->service->createTestRun($this->tenant->id, [$platform->id], $this->user->id);
        $this->service->startTestRun($run->id);

        $this->service->recordTestResult($run->id, $platform->id, $tc1->id, 'passed');
        $this->service->recordTestResult($run->id, $platform->id, $tc2->id, 'failed', '接口返回 500');

        $completed = $this->service->completeTestRun($run->id);

        $this->assertEquals(CompatibilityTestRun::STATUS_FAILED, $completed->status);
        $this->assertEquals(1, $completed->passed_tests);
        $this->assertEquals(1, $completed->failed_tests);
    }

    /** @test */
    public function it_batch_records_results()
    {
        $this->service->initializeFromTemplates($this->tenant->id);
        $suite = $this->service->createSuite($this->tenant->id, ['name' => '批量测试', 'category' => 'integration']);
        $tc1 = $this->service->addTestCase($suite->id, ['name' => '用例1']);
        $tc2 = $this->service->addTestCase($suite->id, ['name' => '用例2']);
        $platform = CompatibilityPlatform::where('tenant_id', $this->tenant->id)
            ->where('category', 'php')->first();

        $run = $this->service->createTestRun($this->tenant->id, [$platform->id]);
        $this->service->startTestRun($run->id);

        $count = $this->service->recordBatchResults($run->id, [
            ['platform_id' => $platform->id, 'test_case_id' => $tc1->id, 'result' => 'passed'],
            ['platform_id' => $platform->id, 'test_case_id' => $tc2->id, 'result' => 'passed'],
        ]);

        $this->assertEquals(2, $count);
    }

    /** @test */
    public function it_gets_stats()
    {
        $this->service->initializeFromTemplates($this->tenant->id);
        $suite = $this->service->createSuite($this->tenant->id, ['name' => '统计测试', 'category' => 'integration']);
        $this->service->addTestCase($suite->id, ['name' => '统计用例']);

        $stats = $this->service->getStats($this->tenant->id);

        $this->assertEquals(15, $stats['total_platforms']);
        $this->assertEquals(1, $stats['total_suites']);
        $this->assertEquals(1, $stats['total_cases']);
    }

    /** @test */
    public function it_gets_run_detail()
    {
        $this->service->initializeFromTemplates($this->tenant->id);
        $suite = $this->service->createSuite($this->tenant->id, ['name' => '详情测试', 'category' => 'api']);
        $tc = $this->service->addTestCase($suite->id, ['name' => '详情用例']);
        $platform = CompatibilityPlatform::where('tenant_id', $this->tenant->id)
            ->where('category', 'php')->first();

        $run = $this->service->createTestRun($this->tenant->id, [$platform->id], $this->user->id);
        $this->service->startTestRun($run->id);
        $this->service->recordTestResult($run->id, $platform->id, $tc->id, 'passed');
        $this->service->completeTestRun($run->id);

        $detail = $this->service->getTestRunDetail($run->id);

        $this->assertArrayHasKey('run', $detail);
        $this->assertArrayHasKey('matrix_by_category', $detail);
        $this->assertArrayHasKey('summary', $detail);
        $this->assertEquals(1, $detail['summary']['passed']);
    }

    /** @test */
    public function it_bulk_adds_test_cases()
    {
        $suite = $this->service->createSuite($this->tenant->id, ['name' => '批量用例', 'category' => 'browser']);

        $cases = $this->service->bulkAddTestCases($suite->id, [
            ['name' => 'Chrome 渲染测试', 'is_critical' => true],
            ['name' => 'Firefox 渲染测试', 'is_critical' => true],
            ['name' => 'Safari 渲染测试'],
        ]);

        $this->assertCount(3, $cases);
    }

    /** @test */
    public function it_initializes_specific_categories()
    {
        $count = $this->service->initializeFromTemplates($this->tenant->id, ['php', 'redis']);

        $this->assertEquals(5, $count); // 3 PHP + 2 Redis
    }
}
