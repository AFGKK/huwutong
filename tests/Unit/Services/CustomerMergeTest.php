<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerMergeService;
use Exception;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class CustomerMergeTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerMergeService $service;
    protected Tenant $tenant;
    protected Customer $source;
    protected Customer $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CustomerMergeService();
        $this->tenant = Tenant::factory()->create();

        $sourceUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $targetUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->source = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $sourceUser->id,
            'type' => 'individual',
            'level' => 'pro',
            'status' => 'active',
            'prepaid_balance' => 100.00,
            'credit_limit' => 500.00,
            'credit_used' => 0,
        ]);

        $this->target = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $targetUser->id,
            'type' => 'enterprise',
            'level' => 'enterprise',
            'status' => 'active',
            'prepaid_balance' => 500.00,
            'credit_limit' => 2000.00,
            'credit_used' => 100.00,
        ]);

        // 给源客户创建一些关联记录
        License::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->source->id,
            'status' => 'active',
        ]);

        Subscription::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->source->id,
        ]);

        Invoice::factory()->count(4)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->source->id,
            'status' => 'paid',
        ]);
    }

    /** @test */
    public function preview_merge_shows_affected_records()
    {
        $preview = $this->service->previewMerge($this->source, $this->target);

        $this->assertEquals($this->source->id, $preview['source']['id']);
        $this->assertEquals($this->target->id, $preview['target']['id']);
        $this->assertEquals(3, $preview['affected_records']['licenses']);
        $this->assertEquals(2, $preview['affected_records']['subscriptions']);
        $this->assertEquals(4, $preview['affected_records']['invoices']);
        $this->assertEquals(100.00, $preview['source']['prepaid_balance']);
        $this->assertEquals(500.00, $preview['target']['prepaid_balance']);
    }

    /** @test */
    public function preview_detects_conflicts()
    {
        $preview = $this->service->previewMerge($this->source, $this->target);

        $this->assertNotEmpty($preview['conflicts']);
        $conflictFields = array_column($preview['conflicts'], 'field');
        $this->assertContains('type', $conflictFields);
        $this->assertContains('level', $conflictFields);
    }

    /** @test */
    public function merge_moves_licenses_to_target()
    {
        $this->service->merge($this->source, $this->target, null, []);

        $this->assertEquals(0, $this->source->licenses()->count());
        $this->assertEquals(3, $this->target->licenses()->count());
    }

    /** @test */
    public function merge_moves_subscriptions_to_target()
    {
        $this->service->merge($this->source, $this->target, null, []);

        $this->assertEquals(0, $this->source->subscriptions()->count());
        $this->assertEquals(2, $this->target->subscriptions()->count());
    }

    /** @test */
    public function merge_moves_invoices_to_target()
    {
        $this->service->merge($this->source, $this->target, null, []);

        $this->assertEquals(0, $this->source->invoices()->count());
        $this->assertEquals(4, $this->target->invoices()->count());
    }

    /** @test */
    public function merge_accumulates_prepaid_balance()
    {
        $this->service->merge($this->source, $this->target, null, []);

        $this->target->refresh();
        $this->source->refresh();

        $this->assertEquals(600.00, (float) $this->target->prepaid_balance);
        $this->assertEquals(2500.00, (float) $this->target->credit_limit);
        $this->assertEquals(100.00, (float) $this->target->credit_used);
    }

    /** @test */
    public function merge_marks_source_as_merged()
    {
        $this->service->merge($this->source, $this->target, null, []);

        $this->source->refresh();

        $this->assertEquals('merged', $this->source->status);
        $this->assertEquals($this->target->id, $this->source->merged_into_customer_id);
    }

    /** @test */
    public function merge_increments_target_merge_count()
    {
        $this->service->merge($this->source, $this->target, null, []);

        $this->target->refresh();
        $this->assertEquals(1, $this->target->merge_count);
    }

    /** @test */
    public function merge_creates_merge_log()
    {
        $log = $this->service->merge($this->source, $this->target, null, ['notes' => 'test merge']);

        $this->assertEquals('completed', $log->status);
        $this->assertEquals($this->source->id, $log->source_customer_id);
        $this->assertEquals($this->target->id, $log->target_customer_id);
        $this->assertNotNull($log->merged_at);
        $this->assertEquals(3, $log->summary['licenses_moved']);
        $this->assertEquals(2, $log->summary['subscriptions_moved']);
        $this->assertEquals(4, $log->summary['invoices_moved']);
    }

    /** @test */
    public function cannot_merge_same_customer()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('不能将客户合并到自身');

        $this->service->merge($this->source, $this->source);
    }

    /** @test */
    public function cannot_merge_across_tenants()
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('只能合并同一租户下的客户');

        $this->service->merge($this->source, $otherCustomer);
    }

    /** @test */
    public function cannot_merge_already_merged_source()
    {
        $this->source->update(['status' => 'merged', 'merged_into_customer_id' => $this->target->id]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('源客户已经是已合并状态');

        $this->service->merge($this->source, $this->target);
    }
}
