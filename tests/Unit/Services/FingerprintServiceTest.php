<?php

namespace Tests\Unit\Services;

use App\Services\FingerprintMatcher;
use App\Services\FingerprintService;
use Tests\TestCase;

class FingerprintServiceTest extends TestCase
{
    private FingerprintService $service;
    private FingerprintMatcher $matcher;

    /** @var array 模拟的真实设备组件数据 */
    private array $realDevice = [
        'mac' => '00:1A:2B:3C:4D:5E',
        'cpu_id' => 'BFEBFBFF000906E9',
        'motherboard' => 'ASUS ROG STRIX Z790-E',
        'disk_sn' => 'XY1234567890ABCD',
        'system_uuid' => '4C4C4544-004C-4410-8053-B4C04F4D3332',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FingerprintService::class);
        $this->matcher = app(FingerprintMatcher::class);
    }

    // ─── FingerprintService Tests ───

    public function test_generates_v2_fingerprint_with_correct_format(): void
    {
        $fp = $this->service->generate($this->realDevice, 2);

        $this->assertMatchesRegularExpression('/^\d+:[a-f0-9]{64}$/', $fp);
        $this->assertStringStartsWith('2:', $fp);
    }

    public function test_generates_v1_fingerprint(): void
    {
        $fp = $this->service->generate($this->realDevice, 1);

        $this->assertStringStartsWith('1:', $fp);
        $this->assertMatchesRegularExpression('/^\d+:[a-f0-9]{64}$/', $fp);
    }

    public function test_same_components_produce_same_fingerprint(): void
    {
        $fp1 = $this->service->generate($this->realDevice);
        $fp2 = $this->service->generate($this->realDevice);

        $this->assertSame($fp1, $fp2);
    }

    public function test_different_components_produce_different_fingerprints(): void
    {
        $deviceB = $this->realDevice;
        $deviceB['mac'] = '00:AA:BB:CC:DD:EE';

        $fp1 = $this->service->generate($this->realDevice);
        $fp2 = $this->service->generate($deviceB);

        $this->assertNotSame($fp1, $fp2);
    }

    public function test_mac_normalization_removes_separators(): void
    {
        $variants = [
            '00:1A:2B:3C:4D:5E',
            '00-1A-2B-3C-4D-5E',
            '001A2B3C4D5E',
            '00:1a:2b:3c:4d:5e',
        ];

        $results = [];
        foreach ($variants as $mac) {
            $components = $this->realDevice;
            $components['mac'] = $mac;
            $results[] = $this->service->generate($components);
        }

        // 所有格式应该产生相同指纹
        $this->assertCount(1, array_unique($results));
    }

    public function test_extracts_version_from_fingerprint(): void
    {
        $fp = $this->service->generate($this->realDevice, 2);
        $this->assertSame(2, $this->service->extractVersion($fp));

        $fpV1 = $this->service->generate($this->realDevice, 1);
        $this->assertSame(1, $this->service->extractVersion($fpV1));
    }

    public function test_validates_fingerprint_format(): void
    {
        $fp = $this->service->generate($this->realDevice);
        $this->assertTrue($this->service->isValidFormat($fp));
        $this->assertFalse($this->service->isValidFormat('invalid-format'));
        $this->assertFalse($this->service->isValidFormat(''));
        $this->assertFalse($this->service->isValidFormat('2:short'));
    }

    public function test_v2_filters_empty_or_placeholder_components(): void
    {
        $components = [
            'mac' => '00:00:00:00:00:00',     // 全零 MAC
            'cpu_id' => 'TO BE FILLED BY OEM', // 占位符
            'motherboard' => 'N/A',
            'disk_sn' => '0000000000000000',   // 全零
            'system_uuid' => '00000000-0000-0000-0000-000000000000', // 全零 UUID
        ];

        $fp = $this->service->generate($components, 2);
        $this->assertMatchesRegularExpression('/^\d+:[a-f0-9]{64}$/', $fp);

        // 验证：此时所有组件都是空，但指纹仍然生成（空字符串参与哈希）
        $components2 = $components;
        $components2['cpu_id'] = 'GENUINEINTEL-001';
        $fp2 = $this->service->generate($components2, 2);

        // 因为 cpu_id 变了，指纹应该不同（避免空组件导致碰撞）
        $this->assertNotSame($fp, $fp2);
    }

    // ─── FingerprintMatcher Tests ───

    public function test_exact_match_same_device(): void
    {
        $result = $this->matcher->match($this->realDevice, $this->realDevice);

        $this->assertTrue($result['matched']);
        $this->assertSame(5, $result['score']);
    }

    public function test_3_out_of_5_match_success(): void
    {
        $deviceB = $this->realDevice;
        $deviceB['mac'] = 'AA:BB:CC:DD:EE:FF';
        $deviceB['cpu_id'] = 'DIFFERENT-CPU-ID';

        $result = $this->matcher->match($this->realDevice, $deviceB, 2);

        $this->assertTrue($result['matched']);  // 3/5 ≥ 3
        $this->assertSame(3, $result['score']);
    }

    public function test_2_out_of_5_match_fails_v2(): void
    {
        $deviceB = $this->realDevice;
        $deviceB['mac'] = 'AA:BB:CC:DD:EE:FF';
        $deviceB['cpu_id'] = 'DIFFERENT-CPU-ID';
        $deviceB['motherboard'] = 'DIFFERENT-MOTHERBOARD';

        $result = $this->matcher->match($this->realDevice, $deviceB, 2);

        $this->assertFalse($result['matched']);  // 2/5 < 3
        $this->assertSame(2, $result['score']);
    }

    public function test_2_out_of_5_match_success_v1(): void
    {
        $deviceB = $this->realDevice;
        $deviceB['mac'] = 'AA:BB:CC:DD:EE:FF';
        $deviceB['cpu_id'] = 'DIFFERENT-CPU-ID';
        $deviceB['motherboard'] = 'DIFFERENT-MOTHERBOARD';

        $result = $this->matcher->match($this->realDevice, $deviceB, 1);

        $this->assertTrue($result['matched']);  // V1 阈值2，2/5 ≥ 2
        $this->assertSame(2, $result['score']);
    }

    public function test_is_match_forgives_minor_changes(): void
    {
        // 模拟：硬盘换了，但其他4个组件一致
        $deviceB = $this->realDevice;
        $deviceB['disk_sn'] = 'NEW-DISK-SN-987654';

        $this->assertTrue($this->matcher->isMatch($this->realDevice, $deviceB));
    }

    public function test_is_match_rejects_too_many_changes(): void
    {
        // 模拟：换了4个组件
        $deviceB = $this->realDevice;
        $deviceB['mac'] = 'AA:BB:CC:DD:EE:FF';
        $deviceB['cpu_id'] = 'DIFFERENT-CPU';
        $deviceB['motherboard'] = 'DIFFERENT-MB';
        $deviceB['disk_sn'] = 'DIFFERENT-DISK';

        $this->assertFalse($this->matcher->isMatch($this->realDevice, $deviceB));
    }

    public function test_similarity_calculation(): void
    {
        $deviceB = $this->realDevice;
        $deviceB['mac'] = 'AA:BB:CC:DD:EE:FF';

        $sim = $this->matcher->similarity($this->realDevice, $deviceB);

        $this->assertSame(80.0, $sim);  // 4/5 = 80%
    }

    public function test_match_returns_detailed_breakdown(): void
    {
        $deviceB = $this->realDevice;
        $deviceB['mac'] = 'AA:BB:CC:DD:EE:FF';

        $result = $this->matcher->match($this->realDevice, $deviceB);

        $this->assertArrayHasKey('details', $result);
        $this->assertArrayHasKey('matched', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('threshold', $result);
        $this->assertArrayHasKey('total_compared', $result);

        $this->assertSame('mismatch', $result['details']['mac']);
        $this->assertSame('match', $result['details']['cpu_id']);
    }
}
