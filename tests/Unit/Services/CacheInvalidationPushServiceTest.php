<?php

namespace Tests\Unit\Services;

use App\Models\CacheInvalidation;
use App\Models\CacheInvalidationWebhook;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CacheInvalidationPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CacheInvalidationPushServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CacheInvalidationPushService $service;
    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CacheInvalidationPushService::class);
        $this->tenant = Tenant::factory()->create(['name' => '测试租户']);
        $this->user = User::factory()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // ─── 创建失效记录 ─────────────────────────────────

    /** @test */
    public function it_creates_cache_invalidation_record()
    {
        $invalidation = $this->service->invalidate(
            tenantId: $this->tenant->id,
            invalidationKey: 'license.status.123',
            type: 'license_status',
            context: ['license_id' => 123, 'new_status' => 'active'],
            immediate: false,
        );

        $this->assertDatabaseHas('cache_invalidations', [
            'id' => $invalidation->id,
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.123',
            'type' => 'license_status',
            'status' => 'pending',
        ]);

        $this->assertEquals('license.status.123', $invalidation->invalidation_key);
        $this->assertEquals('license_status', $invalidation->type);
        $this->assertEquals(['license_id' => 123, 'new_status' => 'active'], $invalidation->context);
    }

    /** @test */
    public function it_creates_feature_flag_invalidation()
    {
        $invalidation = $this->service->invalidate(
            tenantId: $this->tenant->id,
            invalidationKey: 'featureflag.premium_ai',
            type: 'feature_flag',
            context: ['flag_key' => 'premium_ai', 'new_value' => true],
        );

        $this->assertEquals('feature_flag', $invalidation->type);
        $this->assertStringContainsString('premium_ai', $invalidation->invalidation_key);
    }

    // ─── 批量创建 ───────────────────────────────────

    /** @test */
    public function it_creates_batch_invalidations()
    {
        $items = [
            ['key' => 'license.status.1', 'type' => 'license_status', 'context' => ['id' => 1]],
            ['key' => 'license.status.2', 'type' => 'license_status', 'context' => ['id' => 2]],
            ['key' => 'featureflag.feature_x', 'type' => 'feature_flag', 'context' => ['enabled' => true]],
        ];

        $result = $this->service->invalidateBatch($this->tenant->id, $items);

        $this->assertCount(3, $result);
        $this->assertEquals(3, CacheInvalidation::where('tenant_id', $this->tenant->id)->count());
    }

    // ─── 标记状态 ───────────────────────────────────

    /** @test */
    public function it_marks_invalidation_as_published()
    {
        $invalidation = CacheInvalidation::create([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.test',
            'type' => 'license_status',
            'status' => 'pending',
        ]);

        $this->service->markPublished($invalidation, 'reverb');
        $invalidation->refresh();

        $this->assertEquals('published', $invalidation->status);
        $this->assertEquals('reverb', $invalidation->channel);
        $this->assertNotNull($invalidation->published_at);
        $this->assertNotNull($invalidation->last_attempt_at);
    }

    /** @test */
    public function it_marks_invalidation_as_failed()
    {
        $invalidation = CacheInvalidation::create([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.test',
            'type' => 'license_status',
            'status' => 'pending',
        ]);

        $this->service->markFailed($invalidation, '连接超时');
        $invalidation->refresh();

        $this->assertEquals('failed', $invalidation->status);
        $this->assertEquals(1, $invalidation->attempts);
        $this->assertEquals('连接超时', $invalidation->last_error);
    }

    // ─── 获取待处理 ─────────────────────────────────

    /** @test */
    public function it_returns_pending_invalidations()
    {
        CacheInvalidation::create([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.1',
            'type' => 'license_status',
            'status' => 'pending',
            'channel' => 'reverb',
        ]);

        CacheInvalidation::create([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'featureflag.f1',
            'type' => 'feature_flag',
            'status' => 'failed',
            'channel' => 'reverb',
        ]);

        // 已发布的不会返回
        CacheInvalidation::create([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.2',
            'type' => 'license_status',
            'status' => 'published',
            'channel' => 'reverb',
        ]);

        $result = $this->service->getPendingInvalidations($this->tenant->id);

        $this->assertEquals(2, $result['pending_count']);
        $this->assertCount(2, $result['invalidations']);
    }

    /** @test */
    public function it_filters_pending_by_since_timestamp()
    {
        // 使用 DB 直接插入来避免 Eloquent 覆盖 created_at
        \Illuminate\Support\Facades\DB::table('cache_invalidations')->insert([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.old',
            'type' => 'license_status',
            'status' => 'pending',
            'channel' => 'reverb',
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-01 10:00:00',
        ]);

        \Illuminate\Support\Facades\DB::table('cache_invalidations')->insert([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.recent',
            'type' => 'license_status',
            'status' => 'pending',
            'channel' => 'reverb',
            'created_at' => '2026-06-09 10:00:00',
            'updated_at' => '2026-06-09 10:00:00',
        ]);

        $since = '2026-06-01 00:00:00';
        $result = $this->service->getPendingInvalidations($this->tenant->id, $since);

        $this->assertEquals(1, $result['pending_count'], '应只返回 recent 一条记录');
        $this->assertEquals('license.status.recent', $result['invalidations'][0]['key']);
    }

    /** @test */
    public function it_returns_all_when_no_since()
    {
        CacheInvalidation::create([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'k1',
            'type' => 'license_status',
            'status' => 'pending',
            'channel' => 'reverb',
        ]);

        CacheInvalidation::create([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'k2',
            'type' => 'feature_flag',
            'status' => 'pending',
            'channel' => 'reverb',
        ]);

        $result = $this->service->getPendingInvalidations($this->tenant->id);

        $this->assertEquals(2, $result['pending_count']);
    }

    /** @test */
    public function it_marks_pending_as_published_after_fetch()
    {
        CacheInvalidation::create([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.1',
            'type' => 'license_status',
            'status' => 'pending',
            'channel' => 'reverb',
        ]);

        $this->service->getPendingInvalidations($this->tenant->id);

        $pendingCount = CacheInvalidation::where('tenant_id', $this->tenant->id)
            ->where('status', 'pending')->count();

        $this->assertEquals(0, $pendingCount);
    }

    // ─── 清理 ───────────────────────────────────────

    /** @test */
    public function it_prunes_old_invalidations()
    {
        // 使用 DB::insert 避免 Eloquent 覆盖 created_at
        \Illuminate\Support\Facades\DB::table('cache_invalidations')->insert([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.old',
            'type' => 'license_status',
            'status' => 'published',
            'channel' => 'reverb',
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-01 10:00:00',
        ]);

        \Illuminate\Support\Facades\DB::table('cache_invalidations')->insert([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.recent',
            'type' => 'license_status',
            'status' => 'published',
            'channel' => 'reverb',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        \Illuminate\Support\Facades\DB::table('cache_invalidations')->insert([
            'tenant_id' => $this->tenant->id,
            'invalidation_key' => 'license.status.pending',
            'type' => 'license_status',
            'status' => 'pending',
            'channel' => 'reverb',
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-01 10:00:00',
        ]);

        $deleted = $this->service->prune(days: 7);

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseHas('cache_invalidations', ['invalidation_key' => 'license.status.recent']);
        $this->assertDatabaseHas('cache_invalidations', ['invalidation_key' => 'license.status.pending']);
    }

    // ─── 租户隔离 ─────────────────────────────────

    /** @test */
    public function it_isolates_invalidations_by_tenant()
    {
        $tenant2 = Tenant::factory()->create(['name' => '租户2']);

        $this->service->invalidate($this->tenant->id, 'license.t1', 'license_status', immediate: false);
        $this->service->invalidate($tenant2->id, 'license.t2', 'license_status', immediate: false);

        $resultT1 = $this->service->getPendingInvalidations($this->tenant->id);
        $resultT2 = $this->service->getPendingInvalidations($tenant2->id);

        $this->assertEquals(1, $resultT1['pending_count']);
        $this->assertEquals('license.t1', $resultT1['invalidations'][0]['key']);
        $this->assertEquals(1, $resultT2['pending_count']);
        $this->assertEquals('license.t2', $resultT2['invalidations'][0]['key']);
    }

    // ─── 类型常量 ─────────────────────────────────

    /** @test */
    public function it_has_required_type_constants()
    {
        $types = CacheInvalidation::TYPES;

        $this->assertArrayHasKey('license_status', $types);
        $this->assertArrayHasKey('feature_flag', $types);
        $this->assertArrayHasKey('product_config', $types);
    }

    /** @test */
    public function it_creates_webhook_configuration()
    {
        $webhook = CacheInvalidationWebhook::create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://sdk.example.com/cache-callback',
            'secret' => 'test-secret-key',
            'subscribed_types' => ['license_status', 'feature_flag'],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('cache_invalidation_webhooks', [
            'id' => $webhook->id,
            'tenant_id' => $this->tenant->id,
            'url' => 'https://sdk.example.com/cache-callback',
        ]);

        $this->assertTrue($webhook->isSubscribed('license_status'));
        $this->assertTrue($webhook->isSubscribed('feature_flag'));
        $this->assertFalse($webhook->isSubscribed('product_config'));
    }

    /** @test */
    public function it_subscribes_all_types_when_types_empty()
    {
        $webhook = CacheInvalidationWebhook::create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://sdk.example.com/hook',
            'subscribed_types' => null,
            'is_active' => true,
        ]);

        $this->assertTrue($webhook->isSubscribed('license_status'));
        $this->assertTrue($webhook->isSubscribed('feature_flag'));
        $this->assertTrue($webhook->isSubscribed('product_config'));
    }
}
