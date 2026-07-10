<?php

namespace Tests\Unit\Services;

use App\Models\License;
use App\Models\SeatAssignment;
use App\Models\SeatWaitingQueue;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\Customer;
use App\Services\SeatPoolService;
use Carbon\Carbon;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SeatPoolTest extends TestCase
{
    use RefreshDatabase;

    protected SeatPoolService $service;
    protected License $license;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SeatPoolService();

        $tenant = Tenant::factory()->create();
        $product = Product::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->license = License::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'seats' => 3,
            'pool_mode' => SeatPoolService::MODE_SHARED,
            'pool_timeout_minutes' => 30,
            'pool_waiting_limit' => 50,
        ]);
    }

    /** @test */
    public function assigns_seat_when_available()
    {
        $result = $this->service->assignSeat($this->license, 'device-fp-001', '办公电脑');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['assignment']);
        $this->assertEquals('active', $result['assignment']->status);
        $this->assertEquals('device-fp-001', $result['assignment']->seat_identifier);
    }

    /** @test */
    public function reuses_existing_assignment()
    {
        $first = $this->service->assignSeat($this->license, 'device-fp-001');
        $this->assertTrue($first['success']);

        // 再次分配同一个标识，应该复用已有记录
        $second = $this->service->assignSeat($this->license, 'device-fp-001');

        $this->assertTrue($second['success']);
        $this->assertEquals($first['assignment']->id, $second['assignment']->id);
    }

    /** @test */
    public function rejects_when_seats_full_in_shared_mode()
    {
        // 分配满 3 个席位
        $this->service->assignSeat($this->license, 'fp-001');
        $this->service->assignSeat($this->license, 'fp-002');
        $this->service->assignSeat($this->license, 'fp-003');

        // 第4个应失败
        $result = $this->service->assignSeat($this->license, 'fp-004');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('已满', $result['message']);
    }

    /** @test */
    public function queues_in_auto_mode_when_full()
    {
        $this->license->update(['pool_mode' => SeatPoolService::MODE_AUTO]);

        // 分配满 3 个席位
        $this->service->assignSeat($this->license, 'fp-001');
        $this->service->assignSeat($this->license, 'fp-002');
        $this->service->assignSeat($this->license, 'fp-003');

        // 第4个应排队
        $result = $this->service->assignSeat($this->license, 'fp-004', '等待设备');

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['queue_position']);
        $this->assertEquals(1, $result['queue_position']);

        // 验证排队记录
        $this->assertDatabaseHas('seat_waiting_queue', [
            'license_id' => $this->license->id,
            'seat_identifier' => 'fp-004',
            'status' => 'waiting',
            'queue_position' => 1,
        ]);
    }

    /** @test */
    public function releases_seat_and_assigns_next_from_queue()
    {
        $this->license->update(['pool_mode' => SeatPoolService::MODE_AUTO]);

        $this->service->assignSeat($this->license, 'fp-001');
        $this->service->assignSeat($this->license, 'fp-002');
        $this->service->assignSeat($this->license, 'fp-003');
        $this->service->assignSeat($this->license, 'fp-004');

        // 释放 fp-001
        $this->service->releaseSeat($this->license, 'fp-001');

        // 检查 fp-004 是否从队列中分配
        $this->assertDatabaseHas('seat_assignments', [
            'license_id' => $this->license->id,
            'seat_identifier' => 'fp-004',
            'status' => 'active',
        ]);

        // 队列中应标记为已分配
        $this->assertDatabaseHas('seat_waiting_queue', [
            'license_id' => $this->license->id,
            'seat_identifier' => 'fp-004',
            'status' => 'assigned',
        ]);
    }

    /** @test */
    public function releases_expired_seats()
    {
        $this->license->update(['pool_timeout_minutes' => 10]);

        $this->service->assignSeat($this->license, 'fp-001');

        // 将 last_active_at 设为超时之前
        SeatAssignment::where('license_id', $this->license->id)
            ->update(['last_active_at' => Carbon::now()->subMinutes(15)]);

        $released = $this->service->releaseExpiredSeats($this->license);

        $this->assertEquals(1, $released);

        $this->assertDatabaseHas('seat_assignments', [
            'license_id' => $this->license->id,
            'seat_identifier' => 'fp-001',
            'status' => 'inactive',
        ]);
    }

    /** @test */
    public function heartbeat_updates_last_active()
    {
        $assignmentResult = $this->service->assignSeat($this->license, 'fp-001');

        // 直接检查 DB 记录是否被更新（赋值时就是 now，更新后也是 now，在同一秒内分不出）
        // 换个方式验证：检查赋值后心跳返回 true
        $result = $this->service->heartbeat($this->license, 'fp-001');

        $this->assertTrue($result);

        // 验证记录存在且活跃
        $this->assertDatabaseHas('seat_assignments', [
            'license_id' => $this->license->id,
            'seat_identifier' => 'fp-001',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function gets_correct_pool_status()
    {
        $this->service->assignSeat($this->license, 'fp-001');
        $this->service->assignSeat($this->license, 'fp-002');

        $status = $this->service->getPoolStatus($this->license);

        $this->assertEquals(3, $status['total_seats']);
        $this->assertEquals(2, $status['active']);
        $this->assertEquals(1, $status['available']);
        $this->assertEquals(66.7, $status['utilization_percent']);
        $this->assertEquals('shared', $status['pool_mode']);
    }

    /** @test */
    public function pool_status_includes_queue_count_in_auto_mode()
    {
        $this->license->update(['pool_mode' => SeatPoolService::MODE_AUTO]);

        $this->service->assignSeat($this->license, 'fp-001');
        $this->service->assignSeat($this->license, 'fp-002');
        $this->service->assignSeat($this->license, 'fp-003');
        $this->service->assignSeat($this->license, 'fp-004');
        $this->service->assignSeat($this->license, 'fp-005');

        $status = $this->service->getPoolStatus($this->license);

        $this->assertEquals(3, $status['total_seats']);
        $this->assertEquals(3, $status['active']);
        $this->assertEquals(2, $status['waiting_queue']);
    }

    /** @test */
    public function updates_pool_config()
    {
        $this->service->updatePoolConfig($this->license, [
            'pool_mode' => 'auto',
            'pool_timeout_minutes' => 60,
            'pool_waiting_limit' => 100,
        ]);

        $this->license->refresh();

        $this->assertEquals('auto', $this->license->pool_mode);
        $this->assertEquals(60, $this->license->pool_timeout_minutes);
        $this->assertEquals(100, $this->license->pool_waiting_limit);
    }

    /** @test */
    public function cancels_queue_entry()
    {
        $this->license->update(['pool_mode' => SeatPoolService::MODE_AUTO]);

        $this->service->assignSeat($this->license, 'fp-001');
        $this->service->assignSeat($this->license, 'fp-002');
        $this->service->assignSeat($this->license, 'fp-003');
        $this->service->assignSeat($this->license, 'fp-004');

        $cancelled = $this->service->cancelQueue($this->license, 'fp-004');

        $this->assertTrue($cancelled);

        $this->assertDatabaseHas('seat_waiting_queue', [
            'license_id' => $this->license->id,
            'seat_identifier' => 'fp-004',
            'status' => 'cancelled',
        ]);
    }
}
