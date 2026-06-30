<?php

namespace Tests\Unit\Services;

use App\Services\KeyGenerator;
use Tests\TestCase;

class KeyGeneratorTest extends TestCase
{
    private KeyGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(KeyGenerator::class);
    }

    public function test_generates_standard_key_with_correct_format(): void
    {
        $key = $this->generator->generate('standard');
        $this->assertMatchesRegularExpression('/^HWT-STD-[A-F0-9]{16}-[A-F0-9]{4}$/', $key);
    }

    public function test_generates_enterprise_key(): void
    {
        $key = $this->generator->generate('enterprise');
        $this->assertStringStartsWith('HWT-ENT-', $key);
    }

    public function test_generates_trial_key(): void
    {
        $key = $this->generator->generate('trial');
        $this->assertStringStartsWith('HWT-TRIAL-', $key);
    }

    public function test_generates_development_key(): void
    {
        $key = $this->generator->generate('development');
        $this->assertStringStartsWith('HWT-DEV-', $key);
    }

    public function test_generates_professional_key(): void
    {
        $key = $this->generator->generate('professional');
        $this->assertStringStartsWith('HWT-PRO-', $key);
    }

    public function test_infers_type_from_prefix(): void
    {
        $this->assertEquals('trial', $this->generator->inferType('HWT-TRIAL-ABCD1234EF567890-1A2B'));
        $this->assertEquals('professional', $this->generator->inferType('HWT-PRO-ABCD1234EF567890-1A2B'));
        $this->assertEquals('enterprise', $this->generator->inferType('HWT-ENT-ABCD1234EF567890-1A2B'));
        $this->assertEquals('standard', $this->generator->inferType('HWT-STD-ABCD1234EF567890-1A2B'));
        $this->assertEquals('development', $this->generator->inferType('HWT-DEV-ABCD1234EF567890-1A2B'));
        $this->assertNull($this->generator->inferType('INVALID-PREFIX-ABCD1234EF567890-1A2B'));
    }

    public function test_get_readable_type(): void
    {
        $this->assertEquals('试用版', $this->generator->getReadableType('HWT-TRIAL-ABCD1234EF567890-1A2B'));
        $this->assertEquals('专业版', $this->generator->getReadableType('HWT-PRO-ABCD1234EF567890-1A2B'));
        $this->assertEquals('企业版', $this->generator->getReadableType('HWT-ENT-ABCD1234EF567890-1A2B'));
        $this->assertEquals('标准版', $this->generator->getReadableType('HWT-STD-ABCD1234EF567890-1A2B'));
        $this->assertEquals('开发版', $this->generator->getReadableType('HWT-DEV-ABCD1234EF567890-1A2B'));
        $this->assertEquals('未知', $this->generator->getReadableType('AAA-BBB-CCC'));
    }

    public function test_validates_professional_key(): void
    {
        $key = $this->generator->generate('professional');
        $this->assertTrue($this->generator->validateFormat($key));
    }

    public function test_validates_enterprise_key(): void
    {
        $key = $this->generator->generate('enterprise');
        $this->assertTrue($this->generator->validateFormat($key));
    }

    public function test_keys_are_unique(): void
    {
        $keys = [];
        for ($i = 0; $i < 100; $i++) {
            $keys[] = $this->generator->generate();
        }
        $this->assertCount(100, array_unique($keys));
    }

    public function test_batch_generates_correct_count(): void
    {
        $keys = $this->generator->generateBatch('standard', 5);
        $this->assertCount(5, $keys);
    }

    public function test_validates_correct_key(): void
    {
        $key = $this->generator->generate('standard');
        $this->assertTrue($this->generator->validateFormat($key));
    }

    public function test_rejects_invalid_key(): void
    {
        $this->assertFalse($this->generator->validateFormat('HWT-STD-INVALID-KEY'));
        $this->assertFalse($this->generator->validateFormat('INVALID-PREFIX-A3F2C8D1E9B07456-1A2B'));
        $this->assertFalse($this->generator->validateFormat(''));
    }

    public function test_rejects_key_with_wrong_checksum(): void
    {
        $this->assertFalse($this->generator->validateFormat('HWT-STD-A3F2C8D1E9B07456-XXXX'));
    }
}
