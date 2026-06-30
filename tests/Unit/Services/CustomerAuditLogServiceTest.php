<?php

namespace Tests\Unit\Services;

use App\Models\Log;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerAuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerAuditLogService $service;
    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CustomerAuditLogService::class);
        $this->tenant = Tenant::factory()->create(['name' => '测试租户']);
        $this->user = User::factory()->create([
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_can_log_an_audit_event()
    {
        $log = $this->service->log(
            action: 'team.member.invited',
            description: '邀请 test@example.com 加入团队（角色: developer）',
            tenant: $this->tenant,
            user: $this->user,
            payload: ['email' => 'test@example.com', 'role' => 'developer'],
        );

        $this->assertDatabaseHas('logs', [
            'id' => $log->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'team.member.invited',
            'type' => 'audit',
        ]);
        $this->assertEquals('audit', $log->type);
    }

    /** @test */
    public function it_can_log_without_user()
    {
        $log = $this->service->log(
            action: 'system.cleanup',
            description: '系统自动清理过期数据',
            tenant: $this->tenant,
        );

        $this->assertDatabaseHas('logs', [
            'id' => $log->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
            'action' => 'system.cleanup',
        ]);
    }

    /** @test */
    public function it_returns_paginated_audit_logs()
    {
        // 创建 25 条测试日志
        for ($i = 0; $i < 25; $i++) {
            $this->service->log(
                action: 'team.member.invited',
                description: "测试日志 #{$i}",
                tenant: $this->tenant,
                user: $this->user,
            );
        }

        // 查询（每页 10 条）
        $result = $this->service->getAuditLogs($this->tenant, [], 10);

        $this->assertEquals(25, $result->total());
        $this->assertEquals(10, $result->perPage());
        $this->assertCount(10, $result->items());
        $this->assertEquals(3, $result->lastPage());
    }

    /** @test */
    public function it_filters_by_action_prefix()
    {
        $this->service->log(action: 'license.created', description: '创建 License', tenant: $this->tenant, user: $this->user);
        $this->service->log(action: 'device.activated', description: '设备激活', tenant: $this->tenant, user: $this->user);
        $this->service->log(action: 'team.member.invited', description: '邀请成员', tenant: $this->tenant, user: $this->user);

        $result = $this->service->getAuditLogs($this->tenant, ['action_prefix' => 'license.']);
        $this->assertEquals(1, $result->total());

        $result = $this->service->getAuditLogs($this->tenant, ['action_prefix' => 'team.']);
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_filters_by_date_range()
    {
        // 创建昨天的日志
        $this->travelTo(now()->subDays(1));
        $this->service->log(action: 'license.created', description: '昨天的操作', tenant: $this->tenant, user: $this->user);
        $this->travelBack();

        // 创建今天的日志
        $this->service->log(action: 'device.activated', description: '今天的操作', tenant: $this->tenant, user: $this->user);

        // 只查今天
        $result = $this->service->getAuditLogs($this->tenant, [
            'date_from' => now()->startOfDay()->toDateString(),
        ]);
        $this->assertEquals(1, $result->total());

        // 只查昨天
        $result = $this->service->getAuditLogs($this->tenant, [
            'date_from' => now()->subDays(1)->startOfDay()->toDateString(),
            'date_to' => now()->subDays(1)->endOfDay()->toDateString(),
        ]);
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_filters_by_search_keyword()
    {
        $this->service->log(action: 'license.created', description: '创建 License ABC-123', tenant: $this->tenant, user: $this->user);
        $this->service->log(action: 'team.member.invited', description: '邀请 test@example.com 加入团队', tenant: $this->tenant, user: $this->user);

        $result = $this->service->getAuditLogs($this->tenant, ['search' => 'ABC-123']);
        $this->assertEquals(1, $result->total());

        $result = $this->service->getAuditLogs($this->tenant, ['search' => 'test@example.com']);
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_returns_stats()
    {
        $this->service->log(action: 'license.created', description: '操作1', tenant: $this->tenant, user: $this->user);
        $this->service->log(action: 'device.activated', description: '操作2', tenant: $this->tenant, user: $this->user);

        $stats = $this->service->getStats($this->tenant);

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(2, $stats['today']);
        $this->assertArrayHasKey('top_users', $stats);
        $this->assertArrayHasKey('recent_actions', $stats);
        $this->assertArrayHasKey('by_date', $stats);
    }

    /** @test */
    public function it_returns_log_detail()
    {
        $log = $this->service->log(
            action: 'license.created',
            description: '测试详情',
            tenant: $this->tenant,
            user: $this->user,
        );

        $found = $this->service->getAuditLogDetail($this->tenant, $log->id);
        $this->assertNotNull($found);
        $this->assertEquals($log->id, $found->id);

        // 其他租户看不到
        $otherTenant = Tenant::factory()->create();
        $notFound = $this->service->getAuditLogDetail($otherTenant, $log->id);
        $this->assertNull($notFound);
    }

    /** @test */
    public function it_returns_action_categories()
    {
        $categories = $this->service->getActionCategories();

        $this->assertArrayHasKey('license', $categories);
        $this->assertArrayHasKey('device', $categories);
        $this->assertArrayHasKey('team', $categories);
        $this->assertArrayHasKey('payment', $categories);
        $this->assertArrayHasKey('billing', $categories);
        $this->assertArrayHasKey('security', $categories);
        $this->assertArrayHasKey('setting', $categories);

        $this->assertEquals('License 操作', $categories['license']['label']);
        $this->assertEquals('license.', $categories['license']['prefix']);
    }

    /** @test */
    public function it_prunes_old_audit_logs()
    {
        // 创建 100 天前的日志（超出 90 天保留期）
        $this->travelTo(now()->subDays(100));
        $oldLog = $this->service->log(
            action: 'license.created',
            description: '过期日志',
            tenant: $this->tenant,
            user: $this->user,
        );
        $this->travelBack();

        // 创建今天的日志
        $this->service->log(
            action: 'device.activated',
            description: '新日志',
            tenant: $this->tenant,
            user: $this->user,
        );

        // 清理（保留 90 天）
        $deleted = $this->service->prune(90);

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseMissing('logs', ['id' => $oldLog->id]);

        // 新日志还在
        $this->assertDatabaseHas('logs', [
            'description' => '新日志',
            'type' => 'audit',
        ]);
    }

    /** @test */
    public function it_only_prunes_audit_type_logs()
    {
        // 创建 100 天前的 audit 日志
        $this->travelTo(now()->subDays(100));
        $auditLog = Log::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'audit',
            'action' => 'test.action',
            'description' => '过期 audit',
        ]);
        // 创建 100 天前的 security 日志（不应被清理）
        $securityLog = Log::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'security',
            'action' => 'test.security',
            'description' => '过期 security',
        ]);
        $this->travelBack();

        $deleted = $this->service->prune(90);

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseMissing('logs', ['id' => $auditLog->id]);
        $this->assertDatabaseHas('logs', ['id' => $securityLog->id]);
    }

    /** @test */
    public function it_handles_csv_export()
    {
        $this->service->log(
            action: 'license.created',
            description: '导出测试',
            tenant: $this->tenant,
            user: $this->user,
        );

        $export = $this->service->exportCsv($this->tenant);

        $this->assertEquals(['时间', '操作人', '邮箱', '操作类型', '操作描述', 'IP 地址', 'User-Agent'], $export['headers']);

        // 使用 Closure 遍历 generator
        $rowCount = 0;
        $rowData = [];
        foreach ($export['rows'] as $row) {
            $rowCount++;
            $rowData = $row;
        }
        $this->assertEquals(1, $rowCount);
        $this->assertStringContainsString('导出测试', $rowData[4]);
    }

    /** @test */
    public function it_respects_tenant_isolation()
    {
        $tenant2 = Tenant::factory()->create();
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

        // 租户 A 的日志
        $this->service->log(action: 'license.created', description: '租户A操作', tenant: $this->tenant, user: $this->user);
        // 租户 B 的日志
        $this->service->log(action: 'device.activated', description: '租户B操作', tenant: $tenant2, user: $user2);

        // 租户 A 只看得到自己的
        $resultA = $this->service->getAuditLogs($this->tenant);
        $this->assertEquals(1, $resultA->total());
        $this->assertStringContainsString('租户A', $resultA->first()->description);

        // 租户 B 只看得到自己的
        $resultB = $this->service->getAuditLogs($tenant2);
        $this->assertEquals(1, $resultB->total());
        $this->assertStringContainsString('租户B', $resultB->first()->description);
    }
}
