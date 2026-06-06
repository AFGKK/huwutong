<?php

namespace Tests\Unit\Services;

use App\Models\Device;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\DeviceLimiter;
use App\Services\KeyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DeviceLimiterTest extends TestCase
{
    use RefreshDatabase;

    private DeviceLimiter $limiter;
    private License $license;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter = app(DeviceLimiter::class);

        $tenant = Tenant::factory()->create();
        $product = Product::factory()->create();
        $keyGen = app(KeyGenerator::class);

        $this->license = License::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'license_key' => $keyGen->generate('standard'),
            'status' => 'active',
            'max_devices' => 3,
            'expires_at' => now()->addYear(),
        ]);
    }

    protected function tearDown(): void
    {
        // 释放锁
        try {
            $this->limiter->release($this->license);
        } catch (\Exception) {
        }
        parent::tearDown();
    }

    public function test_allows_new_device_when_under_limit(): void
    {
        $result = $this->limiter->acquire($this->license, 'fp-001', 3);

        $this->assertTrue($result->allowed);
        $this->assertFalse($result->isExistingDevice);
    }

    public function test_allows_existing_device_at_limit(): void
    {
        // 先注册设备
        Device::factory()->create([
            'license_id' => $this->license->id,
            'tenant_id' => $this->license->tenant_id,
            'fingerprint' => 'existing-fp',
        ]);

        // 已绑定设备不受限制
        $result = $this->limiter->acquire($this->license, 'existing-fp', 1);

        $this->assertTrue($result->allowed);
        $this->assertTrue($result->isExistingDevice);
    }

    public function test_blocks_new_device_when_at_limit(): void
    {
        // 创建 3 台设备（达到上限）
        for ($i = 0; $i < 3; $i++) {
            Device::factory()->create([
                'license_id' => $this->license->id,
                'tenant_id' => $this->license->tenant_id,
                'fingerprint' => "fp-{$i}",
            ]);
        }

        $result = $this->limiter->acquire($this->license, 'fp-new', 3);

        $this->assertFalse($result->allowed);
        $this->assertSame(3, $result->currentCount);
        $this->assertSame(3, $result->maxDevices);
    }

    public function test_device_count_is_cached(): void
    {
        $cacheKey = 'hwt:device:count:' . $this->license->id;

        // 首次获取应缓存
        $count1 = $this->limiter->getDeviceCount($this->license);
        $this->assertTrue(Cache::has($cacheKey));

        // 添加设备后，缓存尚未刷新，应返回旧值
        Device::factory()->create([
            'license_id' => $this->license->id,
            'tenant_id' => $this->license->tenant_id,
            'fingerprint' => 'fp-cached',
        ]);

        $count2 = $this->limiter->getDeviceCount($this->license);
        $this->assertSame($count1, $count2); // 缓存未刷新，值不变

        // 刷新缓存
        $this->limiter->refreshDeviceCount($this->license);
        $count3 = $this->limiter->getDeviceCount($this->license);
        $this->assertSame(1, $count3);
    }

    public function test_blacklisted_devices_are_excluded_from_count(): void
    {
        Device::factory()->create([
            'license_id' => $this->license->id,
            'tenant_id' => $this->license->tenant_id,
            'fingerprint' => 'fp-blacklisted',
            'is_blacklisted' => true,
        ]);

        $this->limiter->refreshDeviceCount($this->license);
        $count = $this->limiter->getDeviceCount($this->license);

        $this->assertSame(0, $count);
    }

    public function test_lock_blocks_duplicate_requests(): void
    {
        // 第一次获取锁，允许
        $result1 = $this->limiter->acquire($this->license, 'fp-dup', 3);
        $this->assertTrue($result1->allowed);

        // 释放锁
        $this->limiter->release($this->license);

        // 先在数据库创建设备模拟第一次的结果已提交
        Device::factory()->create([
            'license_id' => $this->license->id,
            'tenant_id' => $this->license->tenant_id,
            'fingerprint' => 'fp-dup',
        ]);
        $this->limiter->refreshDeviceCount($this->license);

        // 第二次获取同一指纹应识别为已有设备
        $result2 = $this->limiter->acquire($this->license, 'fp-dup', 3);
        $this->assertTrue($result2->allowed);
        $this->assertTrue($result2->isExistingDevice);
    }
}
