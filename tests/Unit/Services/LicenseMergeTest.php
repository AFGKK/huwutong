<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Device;
use App\Models\License;
use App\Models\LicenseMergeJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LicenseMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseMergeTest extends TestCase
{
    use RefreshDatabase;

    protected LicenseMergeService $service;
    protected Tenant $tenant;
    protected Customer $sourceCustomer;
    protected Customer $targetCustomer;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LicenseMergeService();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->sourceCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->targetCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function createLicense(Customer $customer, string $status = 'active'): License
    {
        return License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => $status,
        ]);
    }

    protected function createDevice(License $license): Device
    {
        return Device::factory()->create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $license->id,
            'fingerprint' => 'v2:' . fake()->sha256(),
        ]);
    }

    /** @test */
    public function preview_merge_shows_correct_counts()
    {
        // 3 active licenses (will be migrated)
        $l1 = $this->createLicense($this->sourceCustomer, 'active');
        $l2 = $this->createLicense($this->sourceCustomer, 'active');
        $l3 = $this->createLicense($this->sourceCustomer, 'suspended');
        $this->createDevice($l1);
        $this->createDevice($l2);

        // 1 expired (will be retired)
        $this->createLicense($this->sourceCustomer, 'expired');

        // 1 pending (will be skipped)
        $this->createLicense($this->sourceCustomer, 'pending');

        $preview = $this->service->previewMerge($this->sourceCustomer, $this->targetCustomer);

        $this->assertEquals(3, $preview['summary']['to_migrate']);
        $this->assertEquals(1, $preview['summary']['to_retire']);
        $this->assertEquals(1, $preview['summary']['to_skip']);
        $this->assertEquals(2, $preview['summary']['devices_to_migrate']);
        $this->assertEquals($this->sourceCustomer->id, $preview['source']['id']);
        $this->assertEquals($this->targetCustomer->id, $preview['target']['id']);
    }

    /** @test */
    public function preview_shows_licenses_grouped_by_status()
    {
        $active = $this->createLicense($this->sourceCustomer, 'active');
        $expired = $this->createLicense($this->sourceCustomer, 'expired');
        $pending = $this->createLicense($this->sourceCustomer, 'pending');

        $preview = $this->service->previewMerge($this->sourceCustomer, $this->targetCustomer);

        $this->assertCount(1, $preview['licenses']['migratable']);
        $this->assertCount(1, $preview['licenses']['retirable']);
        $this->assertCount(1, $preview['licenses']['skippable']);
        $this->assertEquals($active->id, $preview['licenses']['migratable'][0]['id']);
    }

    /** @test */
    public function merge_moves_active_licenses_to_target_customer()
    {
        $license = $this->createLicense($this->sourceCustomer, 'active');
        $device = $this->createDevice($license);

        $job = $this->service->merge(
            $this->sourceCustomer,
            $this->targetCustomer,
            $this->user->id
        );

        $this->assertEquals('completed', $job->status);

        // License should now belong to target customer
        $license->refresh();
        $this->assertEquals($this->targetCustomer->id, $license->customer_id);

        // Device tenant should be updated
        $device->refresh();
        $this->assertEquals($this->targetCustomer->tenant_id, $device->tenant_id);

        // Check audit trail
        $this->assertEquals(1, $job->merged_licenses);
        $this->assertEquals(0, $job->skipped_licenses);
    }

    /** @test */
    public function merge_skips_expired_licenses()
    {
        $active = $this->createLicense($this->sourceCustomer, 'active');
        $expired = $this->createLicense($this->sourceCustomer, 'expired');

        $job = $this->service->merge(
            $this->sourceCustomer,
            $this->targetCustomer,
            $this->user->id
        );

        $this->assertEquals('completed', $job->status);
        $this->assertEquals(1, $job->merged_licenses);
        $this->assertEquals(1, $job->skipped_licenses);

        // Expired license should still belong to source (not migrated)
        $expired->refresh();
        $this->assertEquals($this->sourceCustomer->id, $expired->customer_id);
    }

    /** @test */
    public function merge_records_audit_trail_on_license_metadata()
    {
        $license = $this->createLicense($this->sourceCustomer, 'active');

        $this->service->merge(
            $this->sourceCustomer,
            $this->targetCustomer,
            $this->user->id
        );

        $license->refresh();
        $meta = $license->metadata;

        $this->assertIsArray($meta['merge_history']);
        $this->assertCount(1, $meta['merge_history']);
        $this->assertEquals('merged', $meta['merge_history'][0]['action']);
        $this->assertEquals($this->sourceCustomer->id, $meta['merge_history'][0]['from_customer_id']);
        $this->assertEquals($this->targetCustomer->id, $meta['merge_history'][0]['to_customer_id']);
    }

    /** @test */
    public function merge_marks_source_customer_as_merged()
    {
        $this->createLicense($this->sourceCustomer, 'active');

        $this->service->merge(
            $this->sourceCustomer,
            $this->targetCustomer,
            $this->user->id
        );

        $this->sourceCustomer->refresh();
        $this->assertEquals($this->targetCustomer->id, $this->sourceCustomer->merged_into_customer_id);
    }

    /** @test */
    public function merge_fails_when_source_and_target_are_same()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('不能将 License 合并到自身');

        $this->service->merge(
            $this->sourceCustomer,
            $this->sourceCustomer,
            $this->user->id
        );
    }

    /** @test */
    public function merge_fails_for_different_tenants()
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('只能合并同一租户下的客户');

        $this->service->merge(
            $this->sourceCustomer,
            $otherCustomer,
            $this->user->id
        );
    }

    /** @test */
    public function rollback_restores_licenses_to_source()
    {
        $license = $this->createLicense($this->sourceCustomer, 'active');
        $device = $this->createDevice($license);

        $job = $this->service->merge(
            $this->sourceCustomer,
            $this->targetCustomer,
            $this->user->id
        );

        // Verify moved
        $license->refresh();
        $this->assertEquals($this->targetCustomer->id, $license->customer_id);

        // Rollback
        $rolledBack = $this->service->rollback($job);

        $this->assertEquals('rolled_back', $rolledBack->status);

        $license->refresh();
        $this->assertEquals($this->sourceCustomer->id, $license->customer_id);

        // Device restored
        $device->refresh();
        $this->assertEquals($this->sourceCustomer->tenant_id, $device->tenant_id);
    }

    /** @test */
    public function get_merge_history_returns_paginated_results()
    {
        LicenseMergeJob::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $history = $this->service->getMergeHistory($this->tenant->id, 3);

        $this->assertEquals(5, $history->total());
        $this->assertCount(3, $history->items());
    }

    /** @test */
    public function merge_records_comprehensive_audit_chain()
    {
        $license = $this->createLicense($this->sourceCustomer, 'active');
        $device = $this->createDevice($license);

        $job = $this->service->merge(
            $this->sourceCustomer,
            $this->targetCustomer,
            $this->user->id
        );

        $audit = $job->merge_audit;
        $this->assertIsArray($audit);
        $this->assertGreaterThanOrEqual(2, count($audit)); // initiated + license_merged + completed

        $actions = array_column($audit, 'action');
        $this->assertContains('initiated', $actions);
        $this->assertContains('license_merged', $actions);
        $this->assertContains('completed', $actions);

        // Find the license_merged entry
        $mergedEntry = collect($audit)->firstWhere('action', 'license_merged');
        $this->assertEquals($license->id, $mergedEntry['license_id']);
        $this->assertEquals(1, $mergedEntry['devices_transferred']);
    }

    /** @test */
    public function can_rollback_only_completed_merge()
    {
        $job = LicenseMergeJob::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('只能回滚已完成的合并');

        $this->service->rollback($job);
    }

    /** @test */
    public function no_licenses_returns_empty_preview()
    {
        $preview = $this->service->previewMerge($this->sourceCustomer, $this->targetCustomer);

        $this->assertEquals(0, $preview['summary']['to_migrate']);
        $this->assertEquals(0, $preview['summary']['to_skip']);
        $this->assertEquals(0, $preview['summary']['to_retire']);
    }
}
